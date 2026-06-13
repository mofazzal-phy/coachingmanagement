<?php

namespace Modules\Attendance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Attendance\Models\Attendance;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $attendances = Attendance::query()
            ->when($request->filled('student_name'), function ($query) use ($request) {
                $query->where('student_name', 'like', '%' . $request->string('student_name')->toString() . '%');
            })
            ->when($request->filled('attendance_date'), function ($query) use ($request) {
                $query->whereDate('attendance_date', $request->input('attendance_date'));
            })
            ->latest('attendance_date')
            ->paginate((int) $request->input('per_page', 10));

        return response()->json($attendances);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id' => ['nullable', 'integer'],
            'student_name' => ['required', 'string', 'max:150'],
            'course_name' => ['nullable', 'string', 'max:150'],
            'attendance_date' => ['required', 'date'],
            'status' => ['required', 'in:present,absent,late'],
            'remarks' => ['nullable', 'string'],
        ]);

        $attendance = Attendance::create($validated);

        return response()->json($attendance, 201);
    }

    public function show(Attendance $attendance)
    {
        return response()->json($attendance);
    }

    public function update(Request $request, Attendance $attendance)
    {
        $validated = $request->validate([
            'student_id' => ['nullable', 'integer'],
            'student_name' => ['sometimes', 'required', 'string', 'max:150'],
            'course_name' => ['nullable', 'string', 'max:150'],
            'attendance_date' => ['sometimes', 'required', 'date'],
            'status' => ['sometimes', 'required', 'in:present,absent,late'],
            'remarks' => ['nullable', 'string'],
        ]);

        $attendance->update($validated);

        return response()->json($attendance->refresh());
    }

    public function destroy(Attendance $attendance)
    {
        $attendance->delete();

        return response()->json(['message' => 'Attendance deleted successfully.']);
    }
}
