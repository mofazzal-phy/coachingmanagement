<?php



namespace Modules\Finance\app\Services;



use Illuminate\Support\Collection;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Log;

use Modules\Exam\app\Models\Exam;

use Modules\Exam\app\Models\ExamRoutine;

use Modules\Finance\app\Models\FeeStructure;

use Modules\Finance\app\Models\FeeType;

use Modules\Finance\app\Models\StudentFeeNotification;

use Modules\Enrollment\app\Models\Batch;
use Modules\Enrollment\app\Models\Course;
use Modules\Enrollment\app\Models\Enrollment;
use Modules\Exam\app\Services\ExamBatchChannelPolicyService;

use Modules\Student\app\Models\Student;



class ExamFeeNotificationService

{

    /**

     * Resolve course/batch rows that should have exam fees for an exam.

     *

     * @return array<int, array<string, mixed>>

     */

    public function getExamFeeScope(string $examId): array

    {

        $exam = Exam::with(['class', 'course', 'batch.course.class'])->find($examId);

        if (!$exam) {

            return [];

        }



        $rows = [];

        $seen = [];



        $addRow = function (?string $classId, ?string $courseId, ?string $batchId, array $labels = []) use (&$rows, &$seen) {

            if (!$batchId || !$courseId) {

                return;

            }

            $key = "{$classId}|{$courseId}|{$batchId}";

            if (isset($seen[$key])) {

                return;

            }

            $seen[$key] = true;

            $rows[] = [

                'class_id' => $classId,

                'course_id' => $courseId,

                'batch_id' => $batchId,

                'class_name' => $labels['class_name'] ?? null,

                'course_name' => $labels['course_name'] ?? null,

                'batch_name' => $labels['batch_name'] ?? null,

            ];

        };



        if ($exam->batch_id && $exam->course_id) {

            $addRow(

                $exam->class_id ?? $exam->batch?->course?->class_id,

                $exam->course_id,

                $exam->batch_id,

                [

                    'class_name' => $exam->class?->name ?? $exam->batch?->course?->class?->name,

                    'course_name' => $exam->course?->name ?? $exam->batch?->course?->name,

                    'batch_name' => $exam->batch?->name,

                ]

            );

        }



        $routines = ExamRoutine::with(['batch.course.class', 'course.class'])

            ->where('exam_id', $examId)

            ->whereNotNull('batch_id')

            ->where('status', '!=', 'cancelled')

            ->get();



        foreach ($routines as $routine) {

            $batch = $routine->batch;

            $course = $routine->course ?? $batch?->course;

            $class = $routine->class ?? $course?->class;

            $addRow(

                $class?->id ?? $exam->class_id,

                $course?->id,

                $routine->batch_id,

                [

                    'class_name' => $class?->name,

                    'course_name' => $course?->name,

                    'batch_name' => $batch?->name,

                ]

            );

        }

        $savedStructures = FeeStructure::where('exam_id', $examId)->get();

        foreach ($rows as &$row) {

            $match = $savedStructures->first(function ($structure) use ($row) {

                return $structure->course_id === $row['course_id']

                    && $structure->class_id === $row['class_id'];

            });

            if ($match) {

                $row['amount'] = (float) $match->amount;

                $row['fee_structure_id'] = $match->id;

            } else {

                $row['amount'] = $row['amount'] ?? 0;

            }

        }

        unset($row);

        usort($rows, fn ($a, $b) => strcmp($a['course_name'] ?? '', $b['course_name'] ?? ''));

        return $rows;

    }



    /**

     * Bulk-create per-course exam fee structures without notifying students.

     */

    public function bulkCreateExamFeeStructures(array $data): array

    {

        DB::beginTransaction();

        try {

            $items = collect($data['items'] ?? [])->filter(fn ($item) => !empty($item['enabled']) && (float) ($item['amount'] ?? 0) > 0);

            if ($items->isEmpty()) {

                return ['success' => false, 'message' => 'Please select at least one exam and set an amount.'];

            }



            $created = [];

            foreach ($items as $item) {

                $examId = $item['exam_id'] ?? $data['exam_id'] ?? null;

                if (!$examId) {

                    continue;

                }

                $exam = Exam::find($examId);

                $title = $item['title'] ?? $data['title'] ?? ($exam?->name ? "{$exam->name} Fee" : 'Exam Fee');

                $structure = $this->upsertExamFeeStructure([

                    'academic_session_id' => $data['academic_session_id'],

                    'exam_id' => $examId,

                    'class_id' => $item['class_id'],

                    'course_id' => $item['course_id'],

                    'batch_id' => $item['batch_id'] ?? null,

                    'fee_type_id' => $data['fee_type_id'],

                    'amount' => $item['amount'],

                    'due_date' => $data['due_date'],

                    'event_date' => $data['event_date'] ?? ($exam?->start_date?->format('Y-m-d')),

                    'title' => $title,

                ]);

                $created[] = $structure;

            }

            if ($created === []) {

                return ['success' => false, 'message' => 'No valid exam fee rows to save.'];

            }



            DB::commit();



            return [

                'success' => true,

                'structures_count' => count($created),

                'structures' => collect($created)->map(fn ($s) => $s->load('feeType', 'class')),

                'message' => 'Exam fee saved for ' . count($created) . ' exam(s). Applies to all batches of the selected course. Enable "Exam fee required" on Exam Routine to notify students.',

            ];

        } catch (\Exception $e) {

            DB::rollBack();

            Log::error('Bulk exam fee structure creation failed: ' . $e->getMessage(), [

                'data' => $data,

                'trace' => $e->getTraceAsString(),

            ]);

            return ['success' => false, 'message' => 'Bulk exam fee creation failed: ' . $e->getMessage()];

        }

    }



    /**

     * Dispatch exam-fee notifications to all active students in exam batches.

     */

    /**
     * @param  array<int, string>|null  $onlyBatchIds
     */
    public function dispatchNotificationsForExam(
        string $examId,
        ?string $message = null,
        ?array $onlyBatchIds = null,
        ?string $deliveryChannel = 'offline',
    ): array {

        $exam = Exam::find($examId);

        if (!$exam) {

            return ['success' => false, 'message' => 'Exam not found.'];

        }



        $structures = FeeStructure::with('feeType')

            ->where('exam_id', $examId)

            ->where('status', 'active')

            ->get();



        if ($structures->isEmpty()) {

            return [

                'success' => false,

                'message' => 'No exam fee structures found for this exam. Create fees from Finance → Fee Structures first.',

            ];

        }

        $batchFilter = $onlyBatchIds !== null
            ? collect($onlyBatchIds)->filter()->unique()->values()->all()
            : null;

        $channel = in_array($deliveryChannel, ['online', 'offline'], true) ? $deliveryChannel : 'offline';
        $allowedBatchIds = app(ExamBatchChannelPolicyService::class)
            ->batchIdsForExamChannel($examId, $channel);

        if ($allowedBatchIds->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No batches found in the exam routine grid for this channel. Add routines first.',
            ];
        }

        DB::beginTransaction();

        try {

            $totalNotifications = 0;
            $studentsTouched = 0;



            foreach ($structures as $feeStructure) {

                $batchIds = $this->resolveBatchIdsForStructure($exam, $feeStructure, $channel, $allowedBatchIds);

                if ($batchIds->isEmpty()) {

                    continue;

                }



                foreach ($batchIds as $batchId) {

                    if ($batchFilter !== null && !in_array($batchId, $batchFilter, true)) {
                        continue;
                    }

                    $enrollments = $this->resolveEnrollmentsForExamFee([

                        'batch_id' => $batchId,

                        'course_id' => $feeStructure->course_id,

                        'class_id' => $feeStructure->class_id,

                        'academic_session_id' => $feeStructure->academic_session_id ?: $exam->academic_session_id,

                    ], $exam);



                    $count = $this->createNotificationsForEnrollments(

                        $enrollments,

                        $feeStructure,

                        $exam,

                        [

                            'batch_id' => $batchId,

                            'course_id' => $feeStructure->course_id,

                            'class_id' => $feeStructure->class_id,

                            'academic_session_id' => $feeStructure->academic_session_id,

                            'amount' => $feeStructure->amount,

                            'due_date' => $feeStructure->due_date?->format('Y-m-d'),

                            'event_date' => $feeStructure->event_date?->format('Y-m-d'),

                            'title' => $feeStructure->description,

                            'message' => $message,

                        ]

                    );

                    $totalNotifications += $count['created'];
                    $studentsTouched += $count['touched'];

                }

            }



            DB::commit();



            return [

                'success' => true,

                'notifications_count' => $totalNotifications,

                'students_touched' => $studentsTouched,

                'message' => $studentsTouched > 0

                    ? ($totalNotifications > 0
                        ? "Exam fee notifications sent to {$totalNotifications} student(s)."
                        : "Exam fee reminders updated for {$studentsTouched} student(s).")

                    : 'No active students found for the configured exam fee batches.',

            ];

        } catch (\Exception $e) {

            DB::rollBack();

            Log::error('Exam fee notification dispatch failed: ' . $e->getMessage(), [

                'exam_id' => $examId,

                'trace' => $e->getTraceAsString(),

            ]);

            return ['success' => false, 'message' => 'Notification dispatch failed: ' . $e->getMessage()];

        }

    }



    /**

     * Create an exam fee structure and dispatch notifications (legacy single-batch flow).

     */

    public function createExamFeeWithNotifications(array $data): array

    {

        DB::beginTransaction();

        try {

            $exam = Exam::find($data['exam_id'] ?? null);

            if (!$exam) {

                return ['success' => false, 'message' => 'Exam not found.'];

            }



            $feeStructure = $this->upsertExamFeeStructure($data);

            $enrollments = $this->resolveEnrollmentsForExamFee($data, $exam);



            if ($enrollments->isEmpty()) {

                DB::commit();

                return [

                    'success' => true,

                    'fee_structure' => $feeStructure,

                    'notifications_count' => 0,

                    'message' => 'Fee structure created but no active students found for this exam/batch.',

                ];

            }



            $counts = $this->createNotificationsForEnrollments($enrollments, $feeStructure, $exam, $data);



            DB::commit();



            $examLabel = $exam->name ? " for {$exam->name}" : '';
            $notified = $counts['touched'];



            return [

                'success' => true,

                'fee_structure' => $feeStructure->load('feeType', 'class'),

                'notifications_count' => $counts['created'],

                'message' => 'Exam fee' . $examLabel . ' created and notifications sent to ' . $notified . ' students.',

            ];

        } catch (\Exception $e) {

            DB::rollBack();

            Log::error('Exam fee creation failed: ' . $e->getMessage(), [

                'data' => $data,

                'trace' => $e->getTraceAsString(),

            ]);

            return ['success' => false, 'message' => 'Exam fee creation failed: ' . $e->getMessage()];

        }

    }



    /**

     * @param  array<string, mixed>  $data

     */

    protected function upsertExamFeeStructure(array $data): FeeStructure

    {

        $examId = $data['exam_id'] ?? null;

        $courseId = $data['course_id'] ?? null;



        $query = FeeStructure::query()

            ->where('academic_session_id', $data['academic_session_id'])

            ->where('class_id', $data['class_id'])

            ->where('fee_type_id', $data['fee_type_id']);



        if ($examId) {

            $query->where('exam_id', $examId);

        } else {

            $query->whereNull('exam_id');

        }



        if ($courseId) {

            $query->where('course_id', $courseId);

        } else {

            $query->whereNull('course_id');

        }



        $feeStructure = $query->first();

        $exam = Exam::find($examId);

        $title = $data['title'] ?? ($exam?->name ? "{$exam->name} Fee" : 'Exam Fee');



        $payload = [

            'amount' => $data['amount'],

            'due_date' => $data['due_date'],

            'event_date' => $data['event_date'] ?? null,

            'description' => $title,

            'status' => 'active',

            'exam_id' => $examId,

            'course_id' => $courseId,

        ];



        if ($feeStructure) {

            $feeStructure->update($payload);

            return $feeStructure->fresh();

        }



        return FeeStructure::create(array_merge($payload, [

            'academic_session_id' => $data['academic_session_id'],

            'class_id' => $data['class_id'],

            'fee_type_id' => $data['fee_type_id'],

        ]));

    }



    /**

     * @param  Collection<int, Enrollment>  $enrollments

     */

    /**
     * @return array{created: int, touched: int}
     */
    protected function createNotificationsForEnrollments(

        Collection $enrollments,

        FeeStructure $feeStructure,

        ?Exam $exam,

        array $data

    ): array {

        $feeType = $feeStructure->feeType ?? FeeType::find($feeStructure->fee_type_id);

        $examName = $exam?->name;

        $title = $data['title'] ?? $feeStructure->description ?? ($examName ? "{$examName} Fee" : ($feeType ? $feeType->name . ' Fee' : 'Exam Fee'));

        $amount = (float) ($data['amount'] ?? $feeStructure->amount);

        $dueDate = $data['due_date'] ?? $feeStructure->due_date?->format('Y-m-d');

        $message = $data['message'] ?? "Your {$title} of ৳" . number_format($amount, 0) . " is due by {$dueDate}. Please pay before the deadline.";

        $created = 0;
        $touched = 0;



        foreach ($enrollments as $enrollment) {

            $meta = [

                'class_id' => $data['class_id'] ?? $feeStructure->class_id,

                'batch_id' => $data['batch_id'] ?? $enrollment->batch_id,

                'course_id' => $data['course_id'] ?? $feeStructure->course_id,

                'exam_id' => $data['exam_id'] ?? $exam?->id ?? $feeStructure->exam_id,

                'exam_name' => $examName,

                'fee_type_name' => $feeType ? $feeType->name : null,

                'event_date' => $data['event_date'] ?? ($exam?->start_date?->format('Y-m-d')),

                'academic_session_id' => $data['academic_session_id'] ?? $feeStructure->academic_session_id,

            ];



            $existing = StudentFeeNotification::query()

                ->where('student_id', $enrollment->student_id)

                ->where('enrollment_id', $enrollment->id)

                ->where('type', 'exam_fee')

                ->when(!empty($meta['exam_id']), fn ($q) => $q->where('meta->exam_id', $meta['exam_id']))

                ->whereIn('status', ['unread', 'read', 'paid'])

                ->orderByRaw("CASE status WHEN 'paid' THEN 1 ELSE 0 END")

                ->first();



            if ($existing?->status === 'paid') {

                continue;

            }



            if ($existing) {

                $existing->update([

                    'fee_structure_id' => $feeStructure->id,

                    'title' => $title,

                    'message' => $message,

                    'amount' => $amount,

                    'due_date' => $dueDate,

                    'status' => 'unread',

                    'read_at' => null,

                    'meta' => $meta,

                ]);

                $touched++;

            } else {

                StudentFeeNotification::create([

                    'student_id' => $enrollment->student_id,

                    'enrollment_id' => $enrollment->id,

                    'fee_structure_id' => $feeStructure->id,

                    'title' => $title,

                    'message' => $message,

                    'amount' => $amount,

                    'due_date' => $dueDate,

                    'status' => 'unread',

                    'type' => 'exam_fee',

                    'meta' => $meta,

                ]);

                $created++;
                $touched++;

            }

        }



        return ['created' => $created, 'touched' => $touched];

    }



    /**

     * @return Collection<int, string>

     */

    protected function resolveBatchIdsForStructure(
        Exam $exam,
        FeeStructure $feeStructure,
        ?string $deliveryChannel = null,
        ?Collection $allowedBatchIds = null,
    ): Collection {

        $channel = in_array($deliveryChannel, ['online', 'offline'], true) ? $deliveryChannel : 'offline';
        $routineBatchIds = $allowedBatchIds ?? app(ExamBatchChannelPolicyService::class)
            ->batchIdsForExamChannel($exam->id, $channel);

        if ($routineBatchIds->isEmpty()) {
            return collect();
        }

        $batchQuery = Batch::query()->whereIn('id', $routineBatchIds->all());

        if ($feeStructure->course_id) {
            $batchQuery->where('course_id', $feeStructure->course_id);
        }

        return $batchQuery->pluck('id')->filter()->unique()->values();

    }



    /**

     * @param  array<string, mixed>  $data

     * @return Collection<int, Enrollment>

     */

    protected function resolveEnrollmentsForExamFee(array $data, ?Exam $exam = null): Collection

    {

        $baseQuery = Enrollment::with('student')

            ->where('batch_id', $data['batch_id'])

            ->where('status', 'active');



        if (!empty($data['course_id'])) {

            $courseId = $data['course_id'];

            $baseQuery->whereHas('batch', fn ($batch) => $batch->where('course_id', $courseId));

        }



        $sessionId = $data['academic_session_id'] ?? $exam?->academic_session_id;

        if ($sessionId) {

            $scoped = clone $baseQuery;

            $scoped->where('academic_session_id', $sessionId);

            $enrollments = $this->filterActiveEnrollments($scoped->get());

            if ($enrollments->isNotEmpty()) {

                return $enrollments;

            }

        }



        return $this->filterActiveEnrollments($baseQuery->get());

    }



    /**

     * @param  Collection<int, Enrollment>  $enrollments

     * @return Collection<int, Enrollment>

     */

    protected function filterActiveEnrollments(Collection $enrollments): Collection

    {

        return $enrollments->filter(function (Enrollment $enrollment) {

            if (empty($enrollment->student_id) || !$enrollment->student) {

                return false;

            }

            $status = strtolower((string) ($enrollment->student->status ?? 'active'));

            return !in_array($status, ['inactive', 'suspended', 'left', 'deleted', 'blocked'], true);

        })->values();

    }



    public function getStudentNotifications(string $studentId, array $filters = []): array

    {

        $query = StudentFeeNotification::where('student_id', $studentId)

            ->with(['feeStructure.feeType', 'enrollment.batch.course']);



        if (!empty($filters['status'])) {

            $query->where('status', $filters['status']);

        }



        if (!empty($filters['type'])) {

            $query->where('type', $filters['type']);

        }



        $notifications = $query->orderBy('created_at', 'desc')->get();

        $unreadCount = $notifications->where('status', 'unread')->count();



        return [

            'success' => true,

            'notifications' => $notifications,

            'unread_count' => $unreadCount,

            'total_count' => $notifications->count(),

        ];

    }



    public function markNotificationAsRead(string $notificationId): array

    {

        $notification = StudentFeeNotification::find($notificationId);

        if (!$notification) {

            return ['success' => false, 'message' => 'Notification not found.'];

        }



        $notification->markAsRead();



        return ['success' => true, 'notification' => $notification->fresh()];

    }



    public function markAllAsRead(string $studentId): array

    {

        $count = StudentFeeNotification::where('student_id', $studentId)

            ->where('status', 'unread')

            ->update(['status' => 'read', 'read_at' => now()]);



        return ['success' => true, 'marked_count' => $count];

    }



    public function getNotificationCount(string $studentId): array

    {

        $unreadCount = StudentFeeNotification::where('student_id', $studentId)

            ->where('status', 'unread')

            ->count();



        $pendingCount = StudentFeeNotification::where('student_id', $studentId)

            ->whereIn('status', ['unread', 'read'])

            ->count();



        return [

            'success' => true,

            'unread_count' => $unreadCount,

            'pending_count' => $pendingCount,

        ];

    }



    public function getStudentNotifiedFees(string $studentId): array

    {

        $notifications = StudentFeeNotification::where('student_id', $studentId)

            ->whereIn('status', ['unread', 'read'])

            ->with(['feeStructure.feeType', 'enrollment.batch.course'])

            ->get()

            ->groupBy('enrollment_id');



        $result = [];

        foreach ($notifications as $enrollmentId => $items) {

            $first = $items->first();

            $result[] = [

                'enrollment_id' => $enrollmentId,

                'enrollment_label' => $first->enrollment?->batch?->course?->name . ' - ' . $first->enrollment?->batch?->name,

                'notifications' => $items->map(function ($item) {

                    return [

                        'id' => $item->id,

                        'title' => $item->title,

                        'amount' => $item->amount,

                        'due_date' => $item->due_date?->format('Y-m-d'),

                        'status' => $item->status,

                        'type' => $item->type ?? 'exam_fee',

                        'fee_structure_id' => $item->fee_structure_id,

                        'fee_type_name' => $item->feeStructure?->feeType?->name,

                        'fee_category' => $item->feeStructure?->feeType?->category ?? 'event_based',

                        'assignment_id' => null,

                        'meta' => $item->meta ?? [],

                        'exam_id' => $item->meta['exam_id'] ?? null,

                    ];

                }),

                'total_amount' => $items->sum('amount'),

            ];

        }



        return [

            'success' => true,

            'data' => $result,

        ];

    }

}


