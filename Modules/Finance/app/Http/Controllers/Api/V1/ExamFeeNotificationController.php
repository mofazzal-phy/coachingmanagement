<?php

namespace Modules\Finance\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Finance\app\Services\ExamFeeNotificationService;
use Modules\Finance\app\Services\FeeManagementService;
use Modules\Enrollment\app\Models\Enrollment;
use Modules\Student\app\Models\Student;

class ExamFeeNotificationController extends BaseApiController
{
    protected ExamFeeNotificationService $examFeeService;

    public function __construct(ExamFeeNotificationService $examFeeService)
    {
        $this->examFeeService = $examFeeService;
    }

    /**
     * Admin: Create exam fee and dispatch notifications to students.
     * POST /api/v1/fee/admin/exam-fee/create
     */
    public function createExamFee(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'academic_session_id' => 'required|string|exists:academic_sessions,id',
            'exam_id' => 'required|string|exists:exams,id',
            'class_id' => 'required|string|exists:classes,id',
            'batch_id' => 'required|string|exists:batches,id',
            'course_id' => 'nullable|string|exists:courses,id',
            'fee_type_id' => 'required|string|exists:fee_types,id',
            'amount' => 'required|numeric|min:1',
            'due_date' => 'required|date',
            'event_date' => 'nullable|date',
            'title' => 'nullable|string|max:255',
            'message' => 'nullable|string|max:1000',
        ]);

        $result = $this->examFeeService->createExamFeeWithNotifications($validated);

        if (!$result['success']) {
            return $this->error($result['message']);
        }

        return $this->created($result, $result['message']);
    }

    /**
     * Admin: Get course/batch scope for an exam's fee configuration.
     * GET /api/v1/fee/admin/exam-fee/scope/{examId}
     */
    public function examFeeScope(string $examId): JsonResponse
    {
        $scope = $this->examFeeService->getExamFeeScope($examId);
        return $this->success($scope);
    }

    /**
     * Admin: Bulk-create per-course exam fee structures (no notifications).
     * POST /api/v1/fee/admin/exam-fee/bulk-create
     */
    public function bulkCreateExamFee(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'academic_session_id' => 'required|string|exists:academic_sessions,id',
            'exam_id' => 'nullable|string|exists:exams,id',
            'fee_type_id' => 'required|string|exists:fee_types,id',
            'due_date' => 'required|date',
            'event_date' => 'nullable|date',
            'title' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.exam_id' => 'nullable|string|exists:exams,id',
            'items.*.class_id' => 'required|string|exists:classes,id',
            'items.*.course_id' => 'required|string|exists:courses,id',
            'items.*.batch_id' => 'nullable|string|exists:batches,id',
            'items.*.amount' => 'nullable|numeric|min:0',
            'items.*.enabled' => 'nullable|boolean',
            'items.*.title' => 'nullable|string|max:255',
        ]);

        $result = $this->examFeeService->bulkCreateExamFeeStructures($validated);
        if (!$result['success']) {
            return $this->error($result['message']);
        }

        return $this->created($result, $result['message']);
    }

    /**
     * Admin: Dispatch exam-fee notifications for all configured structures.
     * POST /api/v1/fee/admin/exam-fee/dispatch/{examId}
     */
    public function dispatchExamFeeNotifications(Request $request, string $examId): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'nullable|string|max:1000',
            'batch_id' => 'nullable|string|exists:batches,id',
            'batch_ids' => 'nullable|array',
            'batch_ids.*' => 'string|exists:batches,id',
            'delivery_channel' => 'nullable|string|in:offline,online',
        ]);

        $batchFilter = null;
        if (!empty($validated['batch_id'])) {
            $batchFilter = [$validated['batch_id']];
        } elseif (!empty($validated['batch_ids'])) {
            $batchFilter = array_values($validated['batch_ids']);
        }

        $result = $this->examFeeService->dispatchNotificationsForExam(
            $examId,
            $validated['message'] ?? null,
            $batchFilter,
            $validated['delivery_channel'] ?? 'offline',
        );
        if (!$result['success']) {
            return $this->error($result['message']);
        }

        return $this->success($result, $result['message']);
    }

    /**
     * Student: Get all fee notifications.
     * GET /api/v1/fee/student/notifications
     */
    public function getNotifications(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => 'nullable|string|exists:students,id',
            'status' => 'nullable|in:unread,read,paid,expired',
            'type' => 'nullable|string',
        ]);

        $studentId = $request->student_id ?: $this->resolveAuthenticatedStudentId();
        if (!$studentId) {
            return $this->notFound('Student profile not found.');
        }

        $result = $this->examFeeService->getStudentNotifications(
            $studentId,
            $request->only(['status', 'type'])
        );

        return $this->success($result);
    }

    /**
     * Student: Get notification count (for badge).
     * GET /api/v1/fee/student/notifications/count
     */
    public function getNotificationCount(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => 'nullable|string|exists:students,id',
        ]);

        $studentId = $request->student_id ?: $this->resolveAuthenticatedStudentId();
        if (!$studentId) {
            return $this->notFound('Student profile not found.');
        }

        $result = $this->examFeeService->getNotificationCount($studentId);

        return $this->success($result);
    }

    /**
     * Student: Mark a single notification as read.
     * PUT /api/v1/fee/student/notifications/{id}/read
     */
    public function markAsRead(string $id): JsonResponse
    {
        $result = $this->examFeeService->markNotificationAsRead($id);

        if (!$result['success']) {
            return $this->notFound($result['message']);
        }

        return $this->success($result);
    }

    /**
     * Student: Mark all notifications as read.
     * PUT /api/v1/fee/student/notifications/read-all
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => 'required|string|exists:students,id',
        ]);

        $result = $this->examFeeService->markAllAsRead($request->student_id);

        return $this->success($result);
    }

    /**
     * Student: Get notified fees grouped by enrollment (for payment).
     * GET /api/v1/fee/student/notified-fees
     */
    public function getNotifiedFees(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => 'nullable|string|exists:students,id',
        ]);

        $studentId = $request->student_id ?: $this->resolveAuthenticatedStudentId();
        if (!$studentId) {
            return $this->notFound('Student profile not found.');
        }

        $result = $this->examFeeService->getStudentNotifiedFees($studentId);

        // The service already returns ['success' => true, 'data' => [...]]
        // so we extract the inner data to avoid double-wrapping through BaseApiController::success()
        return $this->success($result['data'] ?? []);
    }

    /**
     * Student: Bulk pay multiple fee assignments at once.
     * POST /api/v1/fee/student/bulk-pay
     */
    public function bulkPay(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enrollment_id' => 'required|string|exists:enrollments,id',
            'student_id' => 'nullable|string|exists:students,id',
            'assignment_ids' => 'required|array|min:1',
            'assignment_ids.*' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|string|in:bkash,nagad,rocket,bank,card,cash',
            'gateway_trx_id' => 'nullable|string|max:255',
            'reference_no' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:500',
        ]);

        // Derive student_id from enrollment if not provided
        if (empty($validated['student_id'])) {
            $enrollment = Enrollment::find($validated['enrollment_id']);
            if (!$enrollment) {
                return $this->notFound('Enrollment not found.');
            }
            $validated['student_id'] = $enrollment->student_id;
        }

        $studentId = $validated['student_id'];
        $enrollmentId = $validated['enrollment_id'];

        \Log::info('[bulkPay] Received assignment_ids', [
            'assignment_ids' => $validated['assignment_ids'],
            'enrollment_id' => $enrollmentId,
            'student_id' => $studentId,
        ]);

        // Separate assignment IDs into:
        // 1. StudentFeeAssignment IDs (monthly fees, etc.)
        // 2. Legacy MonthlyFeeRecord IDs (from old system, need to create smart assignments)
        // 3. Fee structure IDs from notifications (exam fees, etc.)
        $existingAssignmentIds = \Modules\Finance\app\Models\StudentFeeAssignment::whereIn('id', $validated['assignment_ids'])
            ->where('enrollment_id', $enrollmentId)
            ->pluck('id')
            ->toArray();

        \Log::info('[bulkPay] Found existing StudentFeeAssignment IDs', [
            'existingAssignmentIds' => $existingAssignmentIds,
        ]);

        // Check remaining IDs against legacy MonthlyFeeRecord table
        // These are monthly fee records from the old system that don't have smart assignments yet
        $remainingIds = [];
        foreach ($validated['assignment_ids'] as $id) {
            if (!in_array($id, $existingAssignmentIds)) {
                $remainingIds[] = $id;
            }
        }

        $legacyMonthlyRecordIds = [];
        $notificationIds = [];
        $notificationFeeStructureIds = [];
        $paidNotificationIds = [];
        if (!empty($remainingIds)) {
            $legacyRecords = \Modules\Enrollment\app\Models\MonthlyFeeRecord::whereIn('id', $remainingIds)
                ->where('enrollment_id', $enrollmentId)
                ->get();

            foreach ($legacyRecords as $legacyRec) {
                $legacyMonthlyRecordIds[] = $legacyRec->id;

                // Create a StudentFeeAssignment record for this legacy monthly fee
                // so the payment allocation works correctly
                $existingAssn = \Modules\Finance\app\Models\StudentFeeAssignment::where('enrollment_id', $enrollmentId)
                    ->where('period_month', $legacyRec->month)
                    ->whereIn('status', ['pending', 'partial'])
                    ->first();

                if (!$existingAssn) {
                    // Find the monthly fee structure for this enrollment
                    $monthlyFeeStructure = \Modules\Finance\app\Models\FeeStructure::whereHas('feeType', function ($q) {
                        $q->where('category', 'monthly');
                    })
                    ->where('class_id', function ($q) use ($enrollmentId) {
                        $q->select('class_id')
                          ->from('enrollments')
                          ->where('id', $enrollmentId)
                          ->limit(1);
                    })
                    ->first();

                    $amount = $legacyRec->due_amount > 0 ? $legacyRec->due_amount : ($legacyRec->total_monthly_fee ?? 0);

                    $newAssn = \Modules\Finance\app\Models\StudentFeeAssignment::create([
                        'enrollment_id' => $enrollmentId,
                        'fee_structure_id' => $monthlyFeeStructure ? $monthlyFeeStructure->id : null,
                        'original_amount' => $legacyRec->total_monthly_fee ?? $amount,
                        'discounted_amount' => $amount,
                        'final_amount' => $amount,
                        'due_date' => $legacyRec->due_date ?? now()->addDays(30),
                        'period_month' => $legacyRec->month,
                        'status' => 'pending',
                        'late_fee_applied' => 0,
                        'paid_amount' => 0,
                    ]);

                    $existingAssignmentIds[] = $newAssn->id;

                    \Log::info('[bulkPay] Created StudentFeeAssignment for legacy monthly fee', [
                        'legacy_record_id' => $legacyRec->id,
                        'month' => $legacyRec->month,
                        'new_assignment_id' => $newAssn->id,
                        'amount' => $amount,
                    ]);
                } else {
                    $existingAssignmentIds[] = $existingAssn->id;

                    \Log::info('[bulkPay] Using existing StudentFeeAssignment for legacy monthly fee', [
                        'legacy_record_id' => $legacyRec->id,
                        'month' => $legacyRec->month,
                        'existing_assignment_id' => $existingAssn->id,
                    ]);
                }
            }

            foreach ($remainingIds as $id) {
                if (in_array($id, $legacyMonthlyRecordIds)) {
                    continue;
                }
                $notificationIds[] = $id;
            }
        }

        if (!empty($notificationIds)) {
            $notifications = \Modules\Finance\app\Models\StudentFeeNotification::where('student_id', $studentId)
                ->where('enrollment_id', $enrollmentId)
                ->whereIn('id', $notificationIds)
                ->whereIn('status', ['unread', 'read'])
                ->get();

            foreach ($notifications as $notif) {
                $paidNotificationIds[] = $notif->id;
                $existingAssn = \Modules\Finance\app\Models\StudentFeeAssignment::where('enrollment_id', $enrollmentId)
                    ->where('fee_structure_id', $notif->fee_structure_id)
                    ->whereIn('status', ['pending', 'partial', 'overdue'])
                    ->first();

                if (!$existingAssn) {
                    $amount = $notif->amount ?? ($notif->feeStructure?->amount ?? 0);
                    $newAssn = \Modules\Finance\app\Models\StudentFeeAssignment::create([
                        'enrollment_id' => $enrollmentId,
                        'fee_structure_id' => $notif->fee_structure_id,
                        'original_amount' => $amount,
                        'discounted_amount' => $amount,
                        'final_amount' => $amount,
                        'due_date' => $notif->due_date ?? now()->addDays(30),
                        'period_month' => now()->format('Y-m'),
                        'status' => 'pending',
                        'late_fee_applied' => 0,
                        'paid_amount' => 0,
                    ]);
                    $existingAssignmentIds[] = $newAssn->id;
                } else {
                    $existingAssignmentIds[] = $existingAssn->id;
                }
            }

            // Legacy: fee_structure_id based IDs (backward compatibility)
            foreach ($remainingIds as $id) {
                if (in_array($id, $legacyMonthlyRecordIds) || in_array($id, $paidNotificationIds)) {
                    continue;
                }
                $notificationFeeStructureIds[] = $id;
            }
        }

        if (!empty($notificationFeeStructureIds)) {
            $legacyNotifications = \Modules\Finance\app\Models\StudentFeeNotification::where('student_id', $studentId)
                ->where('enrollment_id', $enrollmentId)
                ->whereIn('fee_structure_id', $notificationFeeStructureIds)
                ->whereIn('status', ['unread', 'read'])
                ->get();

            foreach ($legacyNotifications as $notif) {
                $paidNotificationIds[] = $notif->id;
                $existingAssn = \Modules\Finance\app\Models\StudentFeeAssignment::where('enrollment_id', $enrollmentId)
                    ->where('fee_structure_id', $notif->fee_structure_id)
                    ->whereIn('status', ['pending', 'partial', 'overdue'])
                    ->first();

                if (!$existingAssn) {
                    $amount = $notif->amount ?? ($notif->feeStructure?->amount ?? 0);
                    $newAssn = \Modules\Finance\app\Models\StudentFeeAssignment::create([
                        'enrollment_id' => $enrollmentId,
                        'fee_structure_id' => $notif->fee_structure_id,
                        'original_amount' => $amount,
                        'discounted_amount' => $amount,
                        'final_amount' => $amount,
                        'due_date' => $notif->due_date ?? now()->addDays(30),
                        'period_month' => now()->format('Y-m'),
                        'status' => 'pending',
                        'late_fee_applied' => 0,
                        'paid_amount' => 0,
                    ]);
                    $existingAssignmentIds[] = $newAssn->id;
                } else {
                    $existingAssignmentIds[] = $existingAssn->id;
                }
            }
        }

        // Merge all assignment IDs and pass to processPayment
        $validated['assignment_ids'] = $existingAssignmentIds;

        // Use the existing FeeManagementService to process payment
        $feeService = app(FeeManagementService::class);
        $result = $feeService->processPayment($validated);

        if (!$result['success']) {
            return $this->error($result['message']);
        }

        if (!empty($paidNotificationIds)) {
            \Modules\Finance\app\Models\StudentFeeNotification::where('student_id', $studentId)
                ->whereIn('id', array_unique($paidNotificationIds))
                ->whereIn('status', ['unread', 'read'])
                ->update(['status' => 'paid', 'read_at' => now()]);
        }

        return $this->created($result, 'Bulk payment submitted successfully.');
    }

    /**
     * Admin: List all exam fee notifications with student info for manual collection.
     * GET /api/v1/fee/admin/exam-fee/notifications
     */
    public function adminNotifications(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => 'nullable|string|exists:students,id',
            'status' => 'nullable|in:unread,read,paid,expired',
            'type' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = \Modules\Finance\app\Models\StudentFeeNotification::query()
            ->with(['student', 'enrollment.batch.course', 'feeStructure.feeType']);

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $perPage = $request->input('per_page', 20);
        $notifications = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->success($notifications);
    }

    /**
     * Admin: Collect exam fee on behalf of a student (manual payment).
     * POST /api/v1/fee/admin/exam-fee/collect
     */
    public function adminCollectExamFee(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|string|exists:students,id',
            'enrollment_id' => 'required|string|exists:enrollments,id',
            'notification_ids' => 'required|array|min:1',
            'notification_ids.*' => 'required|string|exists:student_fee_notifications,id',
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|string|in:bkash,nagad,rocket,bank,card,cash',
            'remarks' => 'nullable|string|max:500',
        ]);

        // Get the fee structure IDs from the notifications
        $notifications = \Modules\Finance\app\Models\StudentFeeNotification::whereIn('id', $validated['notification_ids'])
            ->where('student_id', $validated['student_id'])
            ->whereIn('status', ['unread', 'read'])
            ->get();

        if ($notifications->isEmpty()) {
            return $this->error('No valid notifications found for this student.');
        }

        // Ensure StudentFeeAssignment records exist for these notified fees
        $feeAssignmentIds = [];
        foreach ($notifications as $notif) {
            $existingAssn = \Modules\Finance\app\Models\StudentFeeAssignment::where('enrollment_id', $validated['enrollment_id'])
                ->where('fee_structure_id', $notif->fee_structure_id)
                ->whereIn('status', ['pending', 'partial'])
                ->first();

            if (!$existingAssn) {
                $feeStructure = $notif->feeStructure;
                $amount = $notif->amount ?? ($feeStructure ? $feeStructure->amount : 0);

                $newAssn = \Modules\Finance\app\Models\StudentFeeAssignment::create([
                    'enrollment_id' => $validated['enrollment_id'],
                    'fee_structure_id' => $notif->fee_structure_id,
                    'original_amount' => $amount,
                    'discounted_amount' => $amount,
                    'final_amount' => $amount,
                    'due_date' => $notif->due_date ?? now()->addDays(30),
                    'period_month' => now()->format('Y-m'),
                    'status' => 'pending',
                    'late_fee_applied' => 0,
                    'paid_amount' => 0,
                    'remarks' => 'Auto-created from exam fee notification (admin collect): ' . ($notif->title ?? ''),
                ]);

                $feeAssignmentIds[] = $newAssn->id;
            } else {
                $feeAssignmentIds[] = $existingAssn->id;
            }
        }

        // Use FeeManagementService to record manual payment with allocations
        $feeService = app(FeeManagementService::class);
        $result = $feeService->recordManualPaymentWithAllocations([
            'enrollment_id' => $validated['enrollment_id'],
            'student_id' => $validated['student_id'],
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'fee_assignment_ids' => $feeAssignmentIds,
            'remarks' => $validated['remarks'] ?? 'Exam fee collection by admin',
            'recorded_by' => auth()->id(),
        ]);

        if (!$result['success']) {
            return $this->error($result['message']);
        }

        // Mark notifications as paid
        \Modules\Finance\app\Models\StudentFeeNotification::whereIn('id', $validated['notification_ids'])
            ->update(['status' => 'paid', 'read_at' => now()]);

        return $this->created($result, 'Exam fee collected successfully on behalf of student.');
    }

    protected function resolveAuthenticatedStudentId(): ?string
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        $student = Student::where('user_id', $user->id)->first();
        if ($student) {
            return $student->id;
        }

        if (!empty($user->email)) {
            $student = Student::where('email', $user->email)->first();
            if ($student) {
                return $student->id;
            }
        }

        if (!empty($user->name)) {
            $student = Student::where('student_id', $user->name)->first();
            if ($student) {
                return $student->id;
            }
        }

        return null;
    }
}
