<?php

namespace Modules\Academic\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Academic\app\Services\ClassRoutineService;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Teacher\app\Models\Teacher;

class TeacherClassRoutineController extends BaseApiController
{
    public function __construct(
        private ClassRoutineService $routineService,
    ) {}

    /**
     * Get the authenticated teacher's weekly schedule.
     */
    public function mySchedule(Request $request): JsonResponse
    {
        $user = auth()->user();

        // Find teacher by user_id
        $teacher = Teacher::where('user_id', $user->id)->first();

        if (!$teacher) {
            return $this->notFound('Teacher profile not found. Please contact admin to link your account.');
        }

        $date = $request->input('date');
        $schedule = $this->routineService->getTeacherSchedule($teacher->id, $date);

        return $this->success($schedule);
    }

    /**
     * Get today's classes for the authenticated teacher.
     */
    public function todayClasses(Request $request): JsonResponse
    {
        $user = auth()->user();
        $teacher = Teacher::where('user_id', $user->id)->first();

        if (!$teacher) {
            return $this->notFound('Teacher profile not found');
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

        $schedule = $this->routineService->getTeacherSchedule($teacher->id);
        $todayRoutines = collect($schedule['flat'] ?? [])
            ->filter(fn($r) => $r['day_of_week'] === $dayKey)
            ->values();

        return $this->success([
            'classes' => $todayRoutines,
            'date' => now()->toDateString(),
            'day' => $dayKey,
            'teacher_name' => $schedule['teacher_name'] ?? '',
        ]);
    }
}
