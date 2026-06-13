<?php

namespace Modules\Exam\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Exam\app\Models\Question;
use Modules\Exam\app\Services\QuestionService;

class QuestionController extends BaseApiController
{
    public function __construct(protected QuestionService $service) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list($request, $request->user());
        $items = $this->service->transformCollection($paginator->items(), false);

        return response()->json([
            'status' => 'success',
            'message' => 'Success',
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $data = $this->service->findForUser($id, $request->user(), true);
        if (!$data) {
            return $this->notFound('Question not found');
        }

        return $this->success($data);
    }

    public function store(Request $request): JsonResponse
    {
        $this->mergeJsonFields($request);
        $validated = $this->validatePayload($request);

        try {
            $question = $this->service->create(
                $validated,
                $request->user(),
                $request->file('attachment')
            );
            $question->load(['subject', 'class', 'course', 'batch', 'creator']);

            return $this->created($this->service->transform($question, true));
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $question = Question::find($id);
        if (!$question) {
            return $this->notFound();
        }

        $this->mergeJsonFields($request);
        $validated = $this->validatePayload($request, $question->question_type);

        try {
            $question = $this->service->update(
                $question,
                $validated,
                $request->user(),
                $request->file('attachment')
            );

            return $this->success($this->service->transform($question, true));
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $question = Question::find($id);
        if (!$question) {
            return $this->notFound();
        }

        try {
            $this->service->delete($question, $request->user());
            return $this->noContent();
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function submit(Request $request, string $id): JsonResponse
    {
        $question = Question::find($id);
        if (!$question) {
            return $this->notFound();
        }

        $request->validate(['comment' => 'nullable|string|max:1000']);

        try {
            $question = $this->service->submit($question, $request->user(), $request->input('comment'));
            return $this->success($this->service->transform($question->load(['subject', 'class']), true), 'Question submitted for review');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $question = Question::find($id);
        if (!$question) {
            return $this->notFound();
        }

        $request->validate(['comment' => 'nullable|string|max:1000']);

        try {
            $question = $this->service->approve($question, $request->user(), $request->input('comment'));
            return $this->success($this->service->transform($question->load(['subject', 'class']), true), 'Question approved');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 403);
        }
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $question = Question::find($id);
        if (!$question) {
            return $this->notFound();
        }

        $request->validate([
            'reason' => 'required|string|max:1000',
            'comment' => 'nullable|string|max:1000',
        ]);

        try {
            $question = $this->service->reject(
                $question,
                $request->user(),
                $request->input('reason'),
                $request->input('comment')
            );
            return $this->success($this->service->transform($question->load(['subject', 'class']), true), 'Question rejected');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 403);
        }
    }

    public function sendBack(Request $request, string $id): JsonResponse
    {
        $question = Question::find($id);
        if (!$question) {
            return $this->notFound();
        }

        $request->validate(['comment' => 'nullable|string|max:1000']);

        try {
            $question = $this->service->sendBack($question, $request->user(), $request->input('comment'));
            return $this->success($this->service->transform($question->load(['subject', 'class']), true), 'Question sent back to draft');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 403);
        }
    }

    public function reviewLogs(Request $request, string $id): JsonResponse
    {
        $question = Question::find($id);
        if (!$question) {
            return $this->notFound();
        }

        try {
            return $this->success($this->service->reviewLogs($question, $request->user()));
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 403);
        }
    }

    public function bulkStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:mcq,cq',
            'shared' => 'required|array',
            'shared.class_id' => 'required|uuid|exists:classes,id',
            'shared.course_id' => 'required|uuid|exists:courses,id',
            'shared.batch_id' => 'required|uuid|exists:batches,id',
            'shared.subject_id' => 'required|uuid|exists:subjects,id',
            'shared.set_title' => 'nullable|string|max:255',
            'shared.chapter' => 'nullable|string|max:255',
            'shared.topic' => 'nullable|string|max:255',
            'shared.difficulty' => 'nullable|in:easy,medium,hard',
            'questions' => 'required|array|min:1|max:100',
            'questions.*.content' => 'nullable|string',
            'questions.*.stimulus' => 'nullable|string',
            'questions.*.section_label' => 'nullable|string|max:255',
            'questions.*.marks' => 'nullable|numeric|min:0.25|max:100',
            'questions.*.sort_order' => 'nullable|integer|min:1',
            'questions.*.options' => 'nullable|array',
            'questions.*.correct_index' => 'nullable|integer|min:0',
            'questions.*.parts' => 'nullable|array',
            'questions.*.chapter' => 'nullable|string|max:255',
            'questions.*.topic' => 'nullable|string|max:255',
        ]);

        try {
            $items = $validated['questions'];
            foreach ($items as $i => $item) {
                if ($validated['type'] === 'mcq' && empty(trim($item['content'] ?? ''))) {
                    throw new \RuntimeException('MCQ #' . ($i + 1) . ' requires question text.');
                }
                if ($validated['type'] === 'cq' && empty(trim($item['stimulus'] ?? ''))) {
                    throw new \RuntimeException('CQ #' . ($i + 1) . ' requires stimulus (উদ্দীপক).');
                }
            }

            $result = $this->service->bulkCreate(
                $validated['shared'],
                $validated['questions'],
                $request->user(),
                $validated['type']
            );
            return $this->created($result, count($result['questions']) . ' question(s) saved as draft');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function bulkSubmit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question_set_id' => 'nullable|uuid',
            'question_ids' => 'nullable|array|min:1',
            'question_ids.*' => 'uuid|exists:questions,id',
            'comment' => 'nullable|string|max:1000',
        ]);

        try {
            if (!empty($validated['question_set_id'])) {
                $count = $this->service->bulkSubmitSet(
                    $validated['question_set_id'],
                    $request->user(),
                    $validated['comment'] ?? null
                );
            } elseif (!empty($validated['question_ids'])) {
                $count = $this->service->bulkSubmitIds(
                    $validated['question_ids'],
                    $request->user(),
                    $validated['comment'] ?? null
                );
            } else {
                return $this->error('Provide question_set_id or question_ids', 422);
            }

            return $this->success(['submitted_count' => $count], "{$count} question(s) submitted for review");
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    protected function mergeJsonFields(Request $request): void
    {
        foreach (['options', 'correct_answer'] as $field) {
            $value = $request->input($field);
            if (is_string($value) && $value !== '') {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $request->merge([$field => $decoded]);
                }
            }
        }
    }

    protected function validatePayload(Request $request, ?string $existingType = null): array
    {
        $type = $request->input('question_type', $existingType);

        $rules = [
            'class_id' => 'required|uuid|exists:classes,id',
            'subject_id' => 'required|uuid|exists:subjects,id',
            'course_id' => 'required|uuid|exists:courses,id',
            'batch_id' => 'required|uuid|exists:batches,id',
            'chapter' => 'nullable|string|max:255',
            'topic' => 'nullable|string|max:255',
            'question_type' => 'required|in:mcq,cq,written,practical',
            'difficulty' => 'required|in:easy,medium,hard',
            'marks' => 'required|numeric|min:0.25|max:1000',
            'content' => 'required|string',
            'attachment' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,pdf|max:5120',
        ];

        if ($type === 'mcq') {
            $rules['options'] = 'required|array|min:2';
            $rules['options.*'] = 'required|string|max:500';
            $rules['correct_answer'] = 'required';
        } else {
            $rules['options'] = 'nullable|array';
            $rules['correct_answer'] = 'nullable';
        }

        $validated = $request->validate($rules);

        if ($type === 'mcq') {
            $validated['correct_answer'] = $this->normalizeMcqCorrectAnswer(
                $validated['correct_answer'],
                $validated['options']
            );
            $validated['options'] = array_values($validated['options']);
        } else {
            $validated['options'] = null;
            $validated['correct_answer'] = $validated['correct_answer'] ?? null;
        }

        unset($validated['attachment']);

        return $validated;
    }

    protected function normalizeMcqCorrectAnswer(mixed $correct, array $options): array
    {
        if (is_string($correct)) {
            $decoded = json_decode($correct, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $correct = $decoded;
            }
        }

        if (is_numeric($correct)) {
            $index = (int) $correct;
            if (!isset($options[$index])) {
                throw new \InvalidArgumentException('Invalid correct answer index.');
            }

            return ['index' => $index, 'value' => $options[$index]];
        }

        if (is_array($correct) && isset($correct['index'])) {
            return $correct;
        }

        throw new \InvalidArgumentException('MCQ requires a valid correct answer.');
    }
}
