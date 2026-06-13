<?php

namespace Modules\Exam\app\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\app\Services\FileUploadService;
use Modules\Exam\app\Models\Question;
use Modules\Exam\app\Models\QuestionReviewLog;
use Modules\Teacher\app\Models\Teacher;

class QuestionService
{
    public function __construct(protected FileUploadService $fileUpload) {}

    public function list(Request $request, User $user): LengthAwarePaginator
    {
        $perPage = min((int) $request->input('per_page', 20), 100);
        $query = Question::with(['subject', 'class', 'course', 'batch', 'creator', 'approver'])
            ->search($request->input('search'))
            ->filter($request->only([
                'status', 'question_type', 'difficulty', 'subject_id',
                'class_id', 'course_id', 'batch_id', 'created_by',
            ]));

        if ($request->boolean('pending_only')) {
            $query->where('status', 'pending');
        }

        if ($request->boolean('my_only')) {
            $query->where('created_by', $user->id);
        } else {
            $this->applyVisibilityScope($query, $user, $request);
        }

        return $query->orderByDesc('updated_at')->paginate($perPage);
    }

    /**
     * Bulk create MCQ or CQ questions in one set (saved as draft).
     */
    public function bulkCreate(array $shared, array $items, User $user, string $type): array
    {
        if (empty($items)) {
            throw new \RuntimeException('At least one question is required.');
        }

        $setId = (string) Str::uuid();
        $setTitle = $shared['set_title'] ?? null;
        $created = [];

        DB::transaction(function () use ($shared, $items, $user, $type, $setId, $setTitle, &$created) {
            foreach ($items as $index => $item) {
                $sortOrder = $item['sort_order'] ?? ($index + 1);
                $base = [
                    'question_set_id' => $setId,
                    'set_title' => $setTitle,
                    'sort_order' => $sortOrder,
                    'created_by' => $user->id,
                    'class_id' => $shared['class_id'],
                    'subject_id' => $shared['subject_id'],
                    'course_id' => $shared['course_id'] ?? null,
                    'batch_id' => $shared['batch_id'] ?? null,
                    'chapter' => $item['chapter'] ?? ($shared['chapter'] ?? null),
                    'topic' => $item['topic'] ?? ($shared['topic'] ?? null),
                    'difficulty' => $shared['difficulty'] ?? 'medium',
                    'status' => 'draft',
                ];

                if ($type === 'mcq') {
                    $options = array_values($item['options'] ?? []);
                    $correctIndex = (int) ($item['correct_index'] ?? 0);
                    $created[] = Question::create(array_merge($base, [
                        'question_type' => 'mcq',
                        'content' => $item['content'],
                        'stimulus' => $item['stimulus'] ?? null,
                        'marks' => $item['marks'] ?? 1,
                        'options' => $options,
                        'correct_answer' => [
                            'index' => $correctIndex,
                            'value' => $options[$correctIndex] ?? null,
                        ],
                    ]));
                } else {
                    $parts = $this->normalizeCqParts($item['parts'] ?? []);
                    $totalMarks = array_sum(array_column($parts, 'marks'));
                    $created[] = Question::create(array_merge($base, [
                        'question_type' => 'cq',
                        'content' => $item['section_label'] ?? ('CQ Set ' . $sortOrder),
                        'stimulus' => $item['stimulus'] ?? '',
                        'marks' => $totalMarks,
                        'parts' => $parts,
                        'options' => null,
                        'correct_answer' => null,
                    ]));
                }
            }
        });

        return [
            'question_set_id' => $setId,
            'set_title' => $setTitle,
            'count' => count($created),
            'questions' => collect($created)->map(fn ($q) => $this->transform($q, true))->values()->all(),
        ];
    }

    /**
     * Submit all draft/rejected questions in a set for admin review.
     */
    public function bulkSubmitSet(string $setId, User $user, ?string $comment = null): int
    {
        $questions = Question::where('question_set_id', $setId)
            ->where('created_by', $user->id)
            ->whereIn('status', ['draft', 'rejected'])
            ->orderBy('sort_order')
            ->get();

        if ($questions->isEmpty()) {
            throw new \RuntimeException('No draft questions found in this set.');
        }

        foreach ($questions as $question) {
            $this->submit($question, $user, $comment);
        }

        return $questions->count();
    }

    /**
     * Submit multiple questions by ID (must be owned by user, draft/rejected).
     */
    public function bulkSubmitIds(array $ids, User $user, ?string $comment = null): int
    {
        $questions = Question::whereIn('id', $ids)
            ->where('created_by', $user->id)
            ->whereIn('status', ['draft', 'rejected'])
            ->get();

        foreach ($questions as $question) {
            $this->submit($question, $user, $comment);
        }

        return $questions->count();
    }

    protected function normalizeCqParts(array $parts): array
    {
        $defaults = [
            ['key' => 'ka', 'label' => '(ক)', 'marks' => 1],
            ['key' => 'kha', 'label' => '(খ)', 'marks' => 2],
            ['key' => 'ga', 'label' => '(গ)', 'marks' => 3],
            ['key' => 'gha', 'label' => '(ঘ)', 'marks' => 4],
        ];

        $normalized = [];
        foreach ($defaults as $i => $def) {
            $input = $parts[$i] ?? $parts[$def['key']] ?? [];
            $normalized[] = [
                'key' => $def['key'],
                'label' => $def['label'],
                'content' => trim($input['content'] ?? ''),
                'marks' => (float) ($input['marks'] ?? $def['marks']),
            ];
        }

        return $normalized;
    }

    public function findForUser(string $id, User $user, bool $includeCorrectAnswer = false): ?array
    {
        $question = Question::with(['subject', 'class', 'course', 'batch', 'creator', 'approver', 'reviewLogs.reviewer'])
            ->find($id);

        if (!$question || !$this->canView($question, $user)) {
            return null;
        }

        return $this->transform($question, $includeCorrectAnswer || $this->canSeeCorrectAnswer($question, $user));
    }

    public function create(array $data, User $user, ?UploadedFile $attachment = null): Question
    {
        $data['created_by'] = $user->id;
        $data['status'] = 'draft';

        if ($attachment) {
            $data['attachment_path'] = $this->fileUpload->upload($attachment, 'questions/attachments');
        }

        return Question::create($data);
    }

    public function update(Question $question, array $data, User $user, ?UploadedFile $attachment = null): Question
    {
        if (!$this->canEdit($question, $user)) {
            throw new \RuntimeException('This question cannot be edited.');
        }

        if ($attachment) {
            $data['attachment_path'] = $this->fileUpload->replace(
                $attachment,
                $question->attachment_path,
                'questions/attachments'
            );
        }

        if ($question->status === 'rejected') {
            $data['status'] = 'draft';
            $data['rejection_reason'] = null;
        }

        $question->update($data);

        return $question->fresh(['subject', 'class', 'course', 'batch', 'creator']);
    }

    public function delete(Question $question, User $user): void
    {
        if (!$this->canDelete($question, $user)) {
            throw new \RuntimeException('This question cannot be deleted.');
        }

        if ($question->attachment_path) {
            $this->fileUpload->delete($question->attachment_path);
        }

        $question->delete();
    }

    public function submit(Question $question, User $user, ?string $comment = null): Question
    {
        $this->assertCreator($question, $user);

        if (!in_array($question->status, ['draft', 'rejected'], true)) {
            throw new \RuntimeException('Only draft or rejected questions can be submitted.');
        }

        return DB::transaction(function () use ($question, $user, $comment) {
            $question->update([
                'status' => 'pending',
                'rejection_reason' => null,
            ]);

            $this->logReview($question, 'submit', $user->id, $comment);

            return $question->fresh();
        });
    }

    public function approve(Question $question, User $user, ?string $comment = null): Question
    {
        $this->assertAdmin($user);

        if ($question->status !== 'pending') {
            throw new \RuntimeException('Only pending questions can be approved.');
        }

        return DB::transaction(function () use ($question, $user, $comment) {
            $question->update([
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);

            $this->logReview($question, 'approve', $user->id, $comment);

            return $question->fresh();
        });
    }

    public function reject(Question $question, User $user, string $reason, ?string $comment = null): Question
    {
        $this->assertAdmin($user);

        if ($question->status !== 'pending') {
            throw new \RuntimeException('Only pending questions can be rejected.');
        }

        return DB::transaction(function () use ($question, $user, $reason, $comment) {
            $question->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'approved_by' => null,
                'approved_at' => null,
            ]);

            $this->logReview($question, 'reject', $user->id, $comment ?: $reason, ['reason' => $reason]);

            return $question->fresh();
        });
    }

    public function sendBack(Question $question, User $user, ?string $comment = null): Question
    {
        $this->assertAdmin($user);

        if ($question->status !== 'pending') {
            throw new \RuntimeException('Only pending questions can be sent back.');
        }

        return DB::transaction(function () use ($question, $user, $comment) {
            $question->update([
                'status' => 'draft',
                'rejection_reason' => null,
            ]);

            $this->logReview($question, 'send_back', $user->id, $comment);

            return $question->fresh();
        });
    }

    public function reviewLogs(Question $question, User $user): array
    {
        if (!$this->canView($question, $user)) {
            throw new \RuntimeException('Unauthorized.');
        }

        return $question->reviewLogs()
            ->with('reviewer')
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'comment' => $log->comment,
                'metadata' => $log->metadata,
                'reviewed_by' => $log->reviewed_by,
                'reviewer_name' => $log->reviewer?->name,
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->all();
    }

    public function transform(Question $question, bool $includeCorrectAnswer = false): array
    {
        $data = $question->toArray();
        $data['attachment_url'] = $this->fileUpload->url($question->attachment_path);
        $data['creator_name'] = $question->creator?->name;
        $data['approver_name'] = $question->approver?->name;
        $data['subject_name'] = $question->subject?->name;
        $data['class_name'] = $question->class?->name;
        $data['course_name'] = $question->course?->name;
        $data['batch_name'] = $question->batch?->name;

        if (!$includeCorrectAnswer) {
            unset($data['correct_answer']);
        }

        return $data;
    }

    public function transformCollection($questions, bool $includeCorrectAnswer = false): array
    {
        return collect($questions)->map(function ($q) use ($includeCorrectAnswer) {
            return $this->transform($q, $includeCorrectAnswer && $q->status === 'approved');
        })->all();
    }

    protected function applyVisibilityScope($query, User $user, Request $request): void
    {
        if ($this->isAdmin($user)) {
            // Admin review queue and explicit draft filter show everything requested.
            if ($request->boolean('pending_only') || $request->input('status') === 'draft') {
                return;
            }
            // Hide other users' drafts from main bank until they submit for review.
            $query->where(function ($q) use ($user) {
                $q->where('status', '!=', 'draft')
                    ->orWhere('created_by', $user->id);
            });
            return;
        }

        $this->applyTeacherVisibilityScope($query, $user);
    }

    protected function applyTeacherVisibilityScope($query, User $user): void
    {
        if ($this->isAdmin($user)) {
            return;
        }

        if (!$user->hasRole('teacher')) {
            return;
        }

        $subjectIds = $this->teacherSubjectIds($user);

        $query->where(function ($q) use ($user, $subjectIds) {
            $q->where('created_by', $user->id);
            if (!empty($subjectIds)) {
                $q->orWhere(function ($q2) use ($subjectIds) {
                    $q2->where('status', 'approved')->whereIn('subject_id', $subjectIds);
                });
            }
        });
    }

    protected function canView(Question $question, User $user): bool
    {
        if ($this->isAdmin($user)) {
            if ($question->status === 'draft' && $question->created_by !== $user->id) {
                return false;
            }
            return true;
        }

        if ($question->created_by === $user->id) {
            return true;
        }

        if ($question->status === 'approved' && $user->hasRole('teacher')) {
            $subjectIds = $this->teacherSubjectIds($user);
            return in_array($question->subject_id, $subjectIds, true);
        }

        return false;
    }

    protected function canEdit(Question $question, User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $question->status !== 'approved';
        }

        return $question->created_by === $user->id && $question->isEditableByCreator();
    }

    protected function canDelete(Question $question, User $user): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        return $question->created_by === $user->id && in_array($question->status, ['draft', 'rejected'], true);
    }

    protected function canSeeCorrectAnswer(Question $question, User $user): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        return $question->created_by === $user->id;
    }

    protected function assertCreator(Question $question, User $user): void
    {
        if ($question->created_by !== $user->id && !$this->isAdmin($user)) {
            throw new \RuntimeException('Only the question creator can perform this action.');
        }
    }

    protected function assertAdmin(User $user): void
    {
        if (!$this->isAdmin($user)) {
            throw new \RuntimeException('Admin access required.');
        }
    }

    protected function isAdmin(User $user): bool
    {
        return $user->hasRole('super-admin') || $user->hasRole('admin');
    }

    protected function teacherSubjectIds(User $user): array
    {
        $teacher = Teacher::where('user_id', $user->id)->first();
        if (!$teacher) {
            return [];
        }

        return $teacher->subjects()->pluck('subjects.id')->all();
    }

    protected function logReview(Question $question, string $action, ?string $userId, ?string $comment = null, array $metadata = []): void
    {
        QuestionReviewLog::create([
            'question_id' => $question->id,
            'action' => $action,
            'reviewed_by' => $userId,
            'comment' => $comment,
            'metadata' => $metadata ?: null,
            'created_at' => now(),
        ]);
    }
}
