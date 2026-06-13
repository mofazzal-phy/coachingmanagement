<?php

namespace Modules\Exam\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Exam\app\Models\ExamAttempt;
use Modules\Exam\app\Services\ExamPaperService;
use Modules\Exam\app\Support\ResolvesStudentFromAuth;

class ExamAttemptController extends BaseApiController
{
    use ResolvesStudentFromAuth;

    public function __construct(private ExamPaperService $paperService) {}

    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'exam_routine_id' => 'required|string|exists:exam_routines,id',
        ]);

        $studentId = $this->resolveStudentId();
        if (!$studentId) {
            return $this->error('Student profile not linked to this account.', 404);
        }

        try {
            $result = $this->paperService->startAttempt(
                $validated['exam_routine_id'],
                $studentId
            );

            $message = !empty($result['resumed'])
                ? 'Attempt resumed'
                : (!empty($result['is_official']) ? 'Online exam started' : 'Practice attempt started');

            return $this->created($result, $message);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'answers' => 'required|array',
            'submit' => 'sometimes|boolean',
        ]);

        $studentId = $this->resolveStudentId();
        if (!$studentId) {
            return $this->error('Student profile not linked to this account.', 404);
        }

        $attempt = ExamAttempt::where('id', $id)->where('student_id', $studentId)->first();
        if (!$attempt) {
            return $this->notFound('Attempt not found');
        }

        try {
            $result = $this->paperService->saveAttempt(
                $id,
                $studentId,
                $validated['answers'],
                (bool) ($validated['submit'] ?? false)
            );

            $message = ($validated['submit'] ?? false)
                ? ($attempt->is_practice ? 'Practice attempt submitted' : 'Exam submitted')
                : 'Progress saved';

            return $this->success($result, $message);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function submit(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'answers' => 'sometimes|array',
        ]);

        $studentId = $this->resolveStudentId();
        if (!$studentId) {
            return $this->error('Student profile not linked to this account.', 404);
        }

        $attempt = ExamAttempt::where('id', $id)->where('student_id', $studentId)->first();
        if (!$attempt) {
            return $this->notFound('Attempt not found');
        }

        try {
            $answers = $validated['answers'] ?? collect($attempt->answers ?? [])->except(ExamPaperService::ORDER_KEY)->all();
            $result = $this->paperService->saveAttempt($id, $studentId, $answers, true);

            return $this->success($result, 'Exam submitted successfully');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function myAttempts(Request $request): JsonResponse
    {
        $studentId = $this->resolveStudentId();
        if (!$studentId) {
            return $this->error('Student profile not linked to this account.', 404);
        }

        $isPractice = $request->has('is_practice')
            ? filter_var($request->input('is_practice'), FILTER_VALIDATE_BOOLEAN)
            : null;

        $attempts = $this->paperService->getMyAttempts($studentId, $isPractice);

        return $this->success([
            'attempts' => $attempts,
            'total' => $attempts->count(),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $studentId = $this->resolveStudentId();
        if (!$studentId) {
            return $this->error('Student profile not linked to this account.', 404);
        }

        $attempt = ExamAttempt::with(['routine.exam', 'routine.subject'])
            ->where('id', $id)
            ->where('student_id', $studentId)
            ->first();

        if (!$attempt) {
            return $this->notFound('Attempt not found');
        }

        $data = [
            'attempt' => $this->paperService->transformAttempt($attempt),
        ];

        if ($attempt->status === 'submitted') {
            $evaluation = $this->paperService->evaluatePracticeAttempt($attempt);
            if (!$attempt->is_practice) {
                $evaluation = collect($evaluation)->except(['breakdown'])->merge([
                    'breakdown' => collect($evaluation['breakdown'] ?? [])->map(function ($item) {
                        unset($item['correct_answer']);
                        return $item;
                    })->values()->all(),
                ])->all();
            }
            $data['evaluation'] = $evaluation;
        } elseif ($attempt->status === 'in_progress') {
            $meta = $this->paperService->getInProgressPlayerMeta($attempt);
            $data['questions'] = $this->paperService->getAttachedQuestions(
                $attempt->routine,
                false,
                false,
                $attempt->answers[ExamPaperService::ORDER_KEY] ?? null
            );
            $data['saved_answers'] = collect($attempt->answers ?? [])->except(ExamPaperService::ORDER_KEY)->all();
            $data = array_merge($data, $meta);
        }

        return $this->success($data);
    }
}
