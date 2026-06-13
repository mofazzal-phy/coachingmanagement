<?php

namespace Modules\Academic\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Academic\app\Services\ClassRoutineService;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Student\app\Models\Student;

class StudentClassRoutineController extends BaseApiController
{
    public function __construct(
        private ClassRoutineService $routineService,
    ) {}

    /**
     * Get the authenticated student's class routine.
     */
    public function myRoutine(): JsonResponse
    {
        $user = auth()->user();

        // Find student by user_id
        $student = Student::where('user_id', $user->id)->first();

        if (!$student) {
            return $this->notFound('Student profile not found');
        }

        $routine = $this->routineService->getStudentRoutine($student->id);

        return $this->success($routine);
    }

    /**
     * Get today's classes for the authenticated student.
     */
    public function todayClasses(): JsonResponse
    {
        $user = auth()->user();
        $student = Student::where('user_id', $user->id)->first();

        if (!$student) {
            return $this->notFound('Student profile not found');
        }

        $today = strtolower(now()->format('D'));
        $dayMap = [
            'sat' => 'sat', 'sun' => 'sun', 'mon' => 'mon',
            'tue' => 'tue', 'wed' => 'wed', 'thu' => 'thu', 'fri' => 'fri',
        ];
        $dayKey = $dayMap[$today] ?? null;

        if (!$dayKey) {
            return $this->success(['classes' => [], 'date' => now()->toDateString()]);
        }

        $routine = $this->routineService->getStudentRoutine($student->id);
        $todayRoutines = collect($routine['flat'] ?? [])
            ->filter(fn($r) => $r['day_of_week'] === $dayKey)
            ->values();

        return $this->success([
            'classes' => $todayRoutines,
            'date' => now()->toDateString(),
            'day' => $dayKey,
        ]);
    }

    /**
     * Get routine for a specific student by ID (admin/teacher view).
     */
    public function forStudent(string $studentId): JsonResponse
    {
        $routine = $this->routineService->getStudentRoutine($studentId);

        return $this->success($routine);
    }
}
