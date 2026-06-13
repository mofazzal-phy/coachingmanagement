<?php

namespace Modules\Exam\app\Services;

use Illuminate\Support\Collection;
use Modules\Exam\app\Models\Exam;
use Modules\Exam\app\Models\ExamBatchChannelPolicy;
use Modules\Exam\app\Models\ExamRoutine;

class ExamBatchChannelPolicyService
{
    /**
     * @return 'all'|'batch'
     */
    public function policyScope(Exam $exam, string $channel): string
    {
        $scope = $channel === 'online'
            ? ($exam->online_policy_scope ?? 'all')
            : ($exam->offline_policy_scope ?? 'all');

        return $scope === 'batch' ? 'batch' : 'all';
    }

    /**
     * @return array{check_enabled: bool, min_percent: ?float, fee_applicable: bool}
     */
    public function rulesForBatch(Exam $exam, string $channel, string $batchId): array
    {
        $policy = ExamBatchChannelPolicy::query()
            ->where('exam_id', $exam->id)
            ->where('batch_id', $batchId)
            ->where('delivery_channel', $channel)
            ->first();

        if ($policy) {
            return $policy->rulesArray();
        }

        return [
            'check_enabled' => false,
            'min_percent' => null,
            'fee_applicable' => false,
        ];
    }

    /**
     * @return array{check_enabled: bool, min_percent: ?float, fee_applicable: bool}
     */
    public function rulesForScope(Exam $exam, string $channel, ?string $batchId = null): array
    {
        if ($this->policyScope($exam, $channel) === 'batch' && $batchId) {
            return $this->rulesForBatch($exam, $channel, $batchId);
        }

        return $exam->eligibilityRulesForChannel($channel);
    }

    /**
     * @return Collection<int, string>
     */
    public function batchIdsForExamChannel(string $examId, string $channel): Collection
    {
        $query = ExamRoutine::query()
            ->where('exam_id', $examId)
            ->whereNotNull('batch_id')
            ->where('status', '!=', 'cancelled');

        if ($channel === 'online') {
            $query->onlineChannel();
        } else {
            $query->offlineChannel();
        }

        return $query->distinct()->pluck('batch_id')->filter()->unique()->values();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listBatchRows(string $examId, string $channel): array
    {
        $exam = Exam::findOrFail($examId);
        $batchIds = $this->batchIdsForExamChannel($examId, $channel);

        $policies = ExamBatchChannelPolicy::query()
            ->where('exam_id', $examId)
            ->where('delivery_channel', $channel)
            ->whereIn('batch_id', $batchIds->all())
            ->get()
            ->keyBy('batch_id');

        $routineQuery = ExamRoutine::query()
            ->where('exam_id', $examId)
            ->whereIn('batch_id', $batchIds->all())
            ->where('status', '!=', 'cancelled');

        if ($channel === 'online') {
            $routineQuery->onlineChannel();
        } else {
            $routineQuery->offlineChannel();
        }

        $routines = $routineQuery->with('batch')->get()->groupBy('batch_id');

        return $batchIds->map(function (string $batchId) use ($policies, $routines, $exam, $channel) {
            $group = $routines->get($batchId, collect());
            $policy = $policies->get($batchId);
            $batch = $group->first()?->batch;

            $draft = $group->where('status', 'draft')->count();
            $published = $group->where('status', 'published')->count();
            $completed = $group->where('status', 'completed')->count();

            return [
                'batch_id' => $batchId,
                'batch_name' => $batch?->name ?? 'Batch',
                'total_routines' => $group->count(),
                'draft_count' => $draft,
                'published_count' => $published,
                'completed_count' => $completed,
                'is_fully_published' => $group->isNotEmpty() && $draft === 0 && $published > 0,
                'eligibility_check_enabled' => (bool) ($policy?->eligibility_check_enabled ?? false),
                'min_attendance_percent' => $policy?->min_attendance_percent,
                'exam_fee_applicable' => (bool) ($policy?->exam_fee_applicable ?? false),
            ];
        })->values()->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $batchPolicies
     */
    public function saveBatchPolicies(Exam $exam, string $channel, array $batchPolicies): void
    {
        foreach ($batchPolicies as $row) {
            $batchId = $row['batch_id'] ?? null;
            if (!$batchId) {
                continue;
            }

            ExamBatchChannelPolicy::updateOrCreate(
                [
                    'exam_id' => $exam->id,
                    'batch_id' => $batchId,
                    'delivery_channel' => $channel,
                ],
                [
                    'eligibility_check_enabled' => (bool) ($row['eligibility_check_enabled'] ?? false),
                    'min_attendance_percent' => array_key_exists('min_attendance_percent', $row)
                        && $row['min_attendance_percent'] !== '' && $row['min_attendance_percent'] !== null
                        ? (float) $row['min_attendance_percent']
                        : null,
                    'exam_fee_applicable' => (bool) ($row['exam_fee_applicable'] ?? false),
                ],
            );
        }
    }

    public function setPolicyScope(Exam $exam, string $channel, string $scope): Exam
    {
        $scope = $scope === 'batch' ? 'batch' : 'all';

        if ($channel === 'online') {
            $exam->online_policy_scope = $scope;
        } else {
            $exam->offline_policy_scope = $scope;
        }

        $exam->save();

        return $exam->fresh();
    }
}
