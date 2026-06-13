<?php

namespace Modules\Exam\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Exam\app\Models\Exam;
use Modules\Exam\app\Services\ExamBatchChannelPolicyService;
class ExamBatchPolicyController extends BaseApiController
{
    public function __construct(
        private readonly ExamBatchChannelPolicyService $policyService,
    ) {}

    public function index(Request $request, string $examId): JsonResponse
    {
        $exam = Exam::find($examId);
        if (!$exam) {
            return $this->notFound('Exam not found');
        }

        $channel = $this->resolveChannel($request);
        $rules = $exam->eligibilityRulesForChannel($channel);

        return $this->success([
            'exam_id' => $examId,
            'delivery_channel' => $channel,
            'policy_scope' => $this->policyService->policyScope($exam, $channel),
            'global' => [
                'eligibility_check_enabled' => (bool) $rules['check_enabled'],
                'min_attendance_percent' => $rules['min_percent'],
                'exam_fee_applicable' => (bool) $rules['fee_applicable'],
            ],
            'batches' => $this->policyService->listBatchRows($examId, $channel),
        ]);
    }

    public function update(Request $request, string $examId): JsonResponse
    {
        $exam = Exam::find($examId);
        if (!$exam) {
            return $this->notFound('Exam not found');
        }

        $validated = $request->validate([
            'delivery_channel' => 'required|in:offline,online',
            'policy_scope' => 'required|in:all,batch',
            'global' => 'sometimes|array',
            'global.eligibility_check_enabled' => 'sometimes|boolean',
            'global.min_attendance_percent' => 'nullable|numeric|min:0|max:100',
            'global.exam_fee_applicable' => 'sometimes|boolean',
            'batches' => 'sometimes|array',
            'batches.*.batch_id' => 'required_with:batches|string|exists:batches,id',
            'batches.*.eligibility_check_enabled' => 'sometimes|boolean',
            'batches.*.min_attendance_percent' => 'nullable|numeric|min:0|max:100',
            'batches.*.exam_fee_applicable' => 'sometimes|boolean',
        ]);

        $channel = $validated['delivery_channel'];
        $scope = $validated['policy_scope'];

        $this->policyService->setPolicyScope($exam, $channel, $scope);
        $exam = $exam->fresh();

        if ($scope === 'all' && !empty($validated['global'])) {
            $global = $validated['global'];
            $payload = $channel === 'online'
                ? [
                    'online_eligibility_check_enabled' => (bool) ($global['eligibility_check_enabled'] ?? false),
                    'online_min_attendance_percent' => $global['min_attendance_percent'] ?? null,
                    'online_exam_fee_applicable' => (bool) ($global['exam_fee_applicable'] ?? false),
                ]
                : [
                    'eligibility_check_enabled' => (bool) ($global['eligibility_check_enabled'] ?? false),
                    'min_attendance_percent' => $global['min_attendance_percent'] ?? null,
                    'exam_fee_applicable' => (bool) ($global['exam_fee_applicable'] ?? false),
                ];
            $exam->update($payload);
            $exam = $exam->fresh();
        }

        if ($scope === 'batch' && !empty($validated['batches'])) {
            $this->policyService->saveBatchPolicies($exam, $channel, $validated['batches']);
        }

        return $this->success([
            'exam_id' => $examId,
            'delivery_channel' => $channel,
            'policy_scope' => $this->policyService->policyScope($exam, $channel),
            'global' => $exam->eligibilityRulesForChannel($channel),
            'batches' => $this->policyService->listBatchRows($examId, $channel),
        ], 'Channel policies saved');
    }

    /**
     * @return 'offline'|'online'
     */
    protected function resolveChannel(Request $request): string
    {
        $channel = $request->input('delivery_channel', $request->query('delivery_channel', 'offline'));

        return in_array($channel, ['online', 'offline'], true) ? $channel : 'offline';
    }
}
