<?php

namespace Modules\Attendance\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Attendance\app\Models\Attendance;
use Modules\Student\app\Models\Student;
use Modules\Core\app\Http\Controllers\BaseApiController;

class AttendanceController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $this->getPerPage($request);
        $attendance = Attendance::with(['student', 'class', 'section'])
            ->filter($request->only(['status', 'date', 'class_id', 'section_id', 'subject_id', 'student_id']))
            ->orderBy('date', 'desc')
            ->paginate($perPage);
        return $this->paginatedResponse($attendance);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'academic_session_id' => 'required|string|exists:academic_sessions,id',
            'class_id' => 'required|string|exists:classes,id',
            'section_id' => 'nullable|string|exists:sections,id',
            'subject_id' => 'nullable|string|exists:subjects,id',
            'student_id' => 'required|string|exists:students,id',
            'date' => 'required|date',
            'status' => 'required|in:present,absent,late,half-day,holiday',
            'remarks' => 'nullable|string',
        ]);

        $validated['marked_by'] = auth()->id();

        $attendance = Attendance::updateOrCreate(
            [
                'student_id' => $validated['student_id'],
                'date' => $validated['date'],
                'subject_id' => $validated['subject_id'] ?? null,
            ],
            $validated
        );

        return $this->created($attendance->load(['student', 'class', 'section']));
    }

    public function bulkStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'academic_session_id' => 'required|string|exists:academic_sessions,id',
            'class_id' => 'required|string|exists:classes,id',
            'section_id' => 'nullable|string|exists:sections,id',
            'subject_id' => 'nullable|string|exists:subjects,id',
            'date' => 'required|date',
            'records' => 'required|array',
            'records.*.student_id' => 'required|string|exists:students,id',
            'records.*.status' => 'required|in:present,absent,late,half-day,holiday',
            'records.*.remarks' => 'nullable|string',
        ]);

        $userId = auth()->id();
        $created = [];

        foreach ($validated['records'] as $record) {
            $attendance = Attendance::updateOrCreate(
                [
                    'student_id' => $record['student_id'],
                    'date' => $validated['date'],
                    'subject_id' => $validated['subject_id'] ?? null,
                ],
                [
                    'academic_session_id' => $validated['academic_session_id'],
                    'class_id' => $validated['class_id'],
                    'section_id' => $validated['section_id'],
                    'subject_id' => $validated['subject_id'] ?? null,
                    'student_id' => $record['student_id'],
                    'date' => $validated['date'],
                    'status' => $record['status'],
                    'remarks' => $record['remarks'] ?? null,
                    'marked_by' => $userId,
                ]
            );
            $created[] = $attendance;
        }

        return $this->created($created, 'Attendance recorded successfully');
    }

    public function show(string $id): JsonResponse
    {
        $attendance = Attendance::with(['student', 'class', 'section', 'markedBy'])->find($id);
        if (!$attendance) return $this->notFound();
        return $this->success($attendance);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $attendance = Attendance::find($id);
        if (!$attendance) return $this->notFound();

        $validated = $request->validate([
            'status' => 'sometimes|in:present,absent,late,half-day,holiday',
            'remarks' => 'nullable|string',
        ]);

        $attendance->update($validated);
        return $this->success($attendance->fresh(['student', 'class', 'section']));
    }

    public function destroy(string $id): JsonResponse
    {
        $attendance = Attendance::find($id);
        if (!$attendance) return $this->notFound();
        $attendance->delete();
        return $this->noContent();
    }

    public function byClass(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_id' => 'required|string|exists:classes,id',
            'section_id' => 'nullable|string|exists:sections,id',
            'date' => 'required|date',
        ]);

        $students = Student::where('current_class_id', $validated['class_id'])
            ->when($validated['section_id'] ?? null, fn($q, $v) => $q->where('current_section_id', $v))
            ->where('status', 'active')
            ->orderBy('roll_no')
            ->get(['id', 'student_id', 'first_name', 'last_name', 'roll_no']);

        // Get existing attendance for this date
        $existingAtt = Attendance::where('class_id', $validated['class_id'])
            ->where('date', $validated['date'])
            ->when($validated['section_id'] ?? null, fn($q, $v) => $q->where('section_id', $v))
            ->get()
            ->keyBy('student_id');

        $result = $students->map(function ($student) use ($existingAtt) {
            $att = $existingAtt->get($student->id);
            return [
                'id' => $student->id,
                'name' => $student->first_name . ' ' . $student->last_name,
                'student_id' => $student->student_id,
                'roll_no' => $student->roll_no,
                'status' => $att?->status ?? 'present',
                'remarks' => $att?->remarks ?? '',
            ];
        })->values();

        return $this->collectionResponse($result);
    }

    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_id' => 'required|string|exists:classes,id',
            'section_id' => 'required|string|exists:sections,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $summary = Attendance::where('class_id', $validated['class_id'])
            ->where('section_id', $validated['section_id'])
            ->whereBetween('date', [$validated['start_date'], $validated['end_date']])
            ->selectRaw("
                student_id,
                COUNT(*) as total_days,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN status = 'half-day' THEN 1 ELSE 0 END) as half_day
            ")
            ->groupBy('student_id')
            ->with('student')
            ->get();

        return $this->collectionResponse($summary);
    }
}
