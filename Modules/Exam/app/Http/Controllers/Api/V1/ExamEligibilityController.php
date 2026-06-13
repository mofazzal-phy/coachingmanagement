<?php

namespace Modules\Exam\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Exam\app\Models\Exam;
use Modules\Exam\app\Services\ExamBatchChannelPolicyService;
use Modules\Exam\app\Services\ExamEligibilityService;
use RuntimeException;

class ExamEligibilityController extends BaseApiController
{
    public function __construct(
        private readonly ExamEligibilityService $eligibilityService,
        private readonly ExamBatchChannelPolicyService $batchPolicyService,
    ) {}

    public function index(Request $request, string $examId): JsonResponse
    {
        $exam = Exam::find($examId);
        if (!$exam) {
            return $this->notFound('Exam not found');
        }

        $channel = $this->resolveChannel($request);
        $rules = $exam->eligibilityRulesForChannel($channel);
        $summaryOnly = filter_var($request->query('summary_only', false), FILTER_VALIDATE_BOOLEAN);
        $refresh = filter_var($request->query('refresh', false), FILTER_VALIDATE_BOOLEAN);

        if ($summaryOnly) {
            $summary = $this->eligibilityService->summaryForExam($examId, $channel);

            return $this->success([
                'exam_id' => $examId,
                'exam_name' => $exam->name,
                'delivery_channel' => $channel,
                'policy_scope' => $this->batchPolicyService->policyScope($exam, $channel),
                'check_enabled' => $this->eligibilityService->isCheckEnabled($exam, $channel),
                'eligibility_check_enabled' => (bool) $rules['check_enabled'],
                'min_attendance_percent' => $rules['min_percent'],
                'exam_fee_applicable' => (bool) $rules['fee_applicable'],
                'thresholds' => $this->eligibilityService->thresholds($exam, $channel),
                'total_students' => $summary['total'] ?? 0,
                'summary' => $summary,
                'students' => [],
            ]);
        }

        $batchId = $request->filled('batch_id') ? (string) $request->query('batch_id') : null;
        $students = $this->eligibilityService->listForExam($examId, $refresh, $channel, $batchId);

        return $this->success([
            'exam_id' => $examId,
            'exam_name' => $exam->name,
            'delivery_channel' => $channel,
            'policy_scope' => $this->batchPolicyService->policyScope($exam, $channel),
            'batch_id' => $batchId,
            'check_enabled' => $batchId
                ? $this->eligibilityService->isCheckEnabled($exam, $channel, $batchId)
                : $this->eligibilityService->isCheckEnabled($exam, $channel),
            'eligibility_check_enabled' => (bool) $rules['check_enabled'],
            'min_attendance_percent' => $rules['min_percent'],
            'exam_fee_applicable' => (bool) $rules['fee_applicable'],
            'thresholds' => $this->eligibilityService->thresholds($exam, $channel),
            'total_students' => count($students),
            'summary' => $this->eligibilityService->summarizeRows($students),
            'students' => $students,
        ]);
    }

    public function me(Request $request, string $examId): JsonResponse
    {
        $studentId = $this->resolveStudentId();
        if (!$studentId) {
            return $this->error('Student profile not linked to this account.', 404);
        }

        try {
            $channel = $this->resolveChannel($request);
            $status = $this->eligibilityService->getStudentStatus($examId, $studentId, true, $channel);

            return $this->success($status);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function sync(Request $request, string $examId): JsonResponse
    {
        try {
            $batchId = $request->filled('batch_id') ? (string) $request->input('batch_id') : null;
            $result = $this->eligibilityService->evaluateExam(
                $examId,
                $this->resolveChannel($request),
                $batchId,
            );

            return $this->success($result, 'Eligibility recalculated');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function override(Request $request, string $examId): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|string|exists:students,id',
            'reason' => 'required|string|min:3|max:500',
        ]);

        $userId = $request->user()?->id;
        if (!$userId) {
            return $this->error('Unauthorized', 401);
        }

        try {
            $row = $this->eligibilityService->override(
                $examId,
                $validated['student_id'],
                $validated['reason'],
                $userId
            );

            return $this->success($row, 'Eligibility override saved');
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    protected function resolveStudentId(): ?string
    {
        $user = request()->user();
        if (!$user) {
            return null;
        }

        $student = \Modules\Student\app\Models\Student::where('user_id', $user->id)->first();

        return $student?->id;
    }

    /**
     * @return 'offline'|'online'
     */
    protected function resolveChannel(Request $request): string
    {
        $channel = $request->query('delivery_channel', 'offline');

        return in_array($channel, ['online', 'offline'], true) ? $channel : 'offline';
    }
}
