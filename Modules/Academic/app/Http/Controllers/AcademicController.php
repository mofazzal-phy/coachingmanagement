<?php

namespace Modules\Academic\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Academic\Models\AcademicCourse;

class AcademicController extends Controller
{
    public function index(Request $request)
    {
        $courses = AcademicCourse::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where('course_name', 'like', "%{$search}%")
                    ->orWhere('course_code', 'like', "%{$search}%")
                    ->orWhere('batch_name', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate((int) $request->input('per_page', 10));

        return response()->json($courses);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_name' => ['required', 'string', 'max:150'],
            'course_code' => ['required', 'string', 'max:50', 'unique:academic_courses,course_code'],
            'batch_name' => ['required', 'string', 'max:100'],
            'section_name' => ['nullable', 'string', 'max:50'],
            'teacher_name' => ['nullable', 'string', 'max:150'],
            'schedule' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $course = AcademicCourse::create($validated);

        return response()->json($course, 201);
    }

    public function show(AcademicCourse $academic)
    {
        return response()->json($academic);
    }

    public function update(Request $request, AcademicCourse $academic)
    {
        $validated = $request->validate([
            'course_name' => ['sometimes', 'required', 'string', 'max:150'],
            'course_code' => ['sometimes', 'required', 'string', 'max:50', 'unique:academic_courses,course_code,' . $academic->id],
            'batch_name' => ['sometimes', 'required', 'string', 'max:100'],
            'section_name' => ['nullable', 'string', 'max:50'],
            'teacher_name' => ['nullable', 'string', 'max:150'],
            'schedule' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $academic->update($validated);

        return response()->json($academic->refresh());
    }

    public function destroy(AcademicCourse $academic)
    {
        $academic->delete();

        return response()->json(['message' => 'Academic course deleted successfully.']);
    }
}
