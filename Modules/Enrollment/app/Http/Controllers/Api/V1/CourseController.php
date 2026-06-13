<?php

namespace Modules\Enrollment\app\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Core\app\Services\FileUploadService;
use Modules\Enrollment\app\Models\Course;
use Modules\Enrollment\app\Services\EnrollmentService;

class CourseController extends BaseApiController
{
    public function __construct(
        protected EnrollmentService $enrollmentService,
        protected FileUploadService $fileUploadService,
    ) {}

    public function index(Request $request)
    {
        $query = Course::with(['subjects:id,name', 'class:id,name', 'group:id,name'])->withCount('batches');

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        if ($request->filled('group_id')) {
            $query->where('group_id', $request->group_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $courses = $query->orderBy('sort_order')->paginate($request->per_page ?? 15);
        return $this->paginatedResponse($courses);
    }

    public function show($id)
    {
        $course = Course::with([
            'subjects:id,name',
            'class:id,name',
            'group:id,name',
            'batches' => fn($q) => $q->with(['room:id,name', 'teacher:id,first_name,last_name'])->select('id','name','course_id','mode','status','enrolled_count','capacity','days','start_time','end_time'),
        ])->findOrFail($id);

        return $this->success($course);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|in:academic,admission_coaching',
            'level' => 'nullable|string|max:50',
            'class_id' => 'nullable|exists:classes,id',
            'group_id' => 'nullable|exists:academic_groups,id',
            'target' => 'nullable|string|max:255',
            'has_online' => 'boolean',
            'has_offline' => 'boolean',
            'duration_days' => 'nullable|integer',
            'duration_label' => 'nullable|string|max:255',
            'one_time_fee' => 'nullable|numeric|min:0',
            'enrollment_fee' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'learning_outcomes' => 'nullable|string',
            'syllabus' => 'nullable|string',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
            'status' => 'in:active,inactive',
        ]);

        if ($request->hasFile('cover_image')) {
            $validated['cover_image'] = $this->fileUploadService->upload(
                $request->file('cover_image'),
                'courses/thumbnails'
            );
        } else {
            unset($validated['cover_image']);
        }

        $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(4);
        $validated['code'] = 'CRS-' . strtoupper(Str::random(6));

        $course = Course::create($validated);

        return $this->created($course->load(['subjects', 'class', 'group']), 'Course created successfully');
    }

    public function update(Request $request, $id)
    {
        $course = Course::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'category' => 'sometimes|in:academic,admission_coaching',
            'level' => 'nullable|string|max:50',
            'class_id' => 'nullable|exists:classes,id',
            'group_id' => 'nullable|exists:academic_groups,id',
            'target' => 'nullable|string|max:255',
            'has_online' => 'boolean',
            'has_offline' => 'boolean',
            'duration_days' => 'nullable|integer',
            'duration_label' => 'nullable|string|max:255',
            'one_time_fee' => 'nullable|numeric|min:0',
            'enrollment_fee' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'learning_outcomes' => 'nullable|string',
            'syllabus' => 'nullable|string',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
            'status' => 'in:active,inactive',
        ]);

        if ($request->hasFile('cover_image')) {
            $validated['cover_image'] = $this->fileUploadService->replace(
                $course->cover_image,
                $request->file('cover_image'),
                'courses/thumbnails'
            );
        } elseif ($request->boolean('remove_cover_image')) {
            $this->fileUploadService->delete($course->cover_image);
            $validated['cover_image'] = null;
        } else {
            unset($validated['cover_image']);
        }

        unset($validated['remove_cover_image']);

        $course->update($validated);

        return $this->success($course->fresh()->load(['subjects', 'class', 'group']), 'Course updated successfully');
    }

    public function destroy($id)
    {
        $course = Course::findOrFail($id);
        $course->delete();

        return $this->noContent('Course deleted successfully');
    }

    public function assignSubjects(Request $request, $id)
    {
        $course = Course::findOrFail($id);

        $validated = $request->validate([
            'subjects' => 'required|array',
            'subjects.*.subject_id' => 'required|exists:subjects,id',
            'subjects.*.fee' => 'required|numeric|min:0',
            'subjects.*.monthly_fee' => 'nullable|numeric|min:0',
            'subjects.*.teacher_id' => 'nullable|exists:teachers,id',
            'subjects.*.is_optional' => 'boolean',
            'subjects.*.is_mandatory' => 'boolean',
            'subjects.*.sort_order' => 'integer',
        ]);

        $syncData = [];
        foreach ($validated['subjects'] as $subject) {
            $syncData[$subject['subject_id']] = [
                'fee' => $subject['fee'],
                'monthly_fee' => $subject['monthly_fee'] ?? 0,
                'teacher_id' => $subject['teacher_id'] ?? null,
                'is_optional' => $subject['is_optional'] ?? false,
                'is_mandatory' => $subject['is_mandatory'] ?? true,
                'sort_order' => $subject['sort_order'] ?? 0,
            ];
        }

        $course->subjects()->sync($syncData);

        return $this->success($course->fresh()->load('subjects'), 'Subjects assigned successfully');
    }

    public function listAll()
    {
        $courses = \Illuminate\Support\Facades\Cache::remember('courses.list_all', 3600, fn() =>
            Course::active()->orderBy('sort_order')->get(['id', 'name', 'code', 'category', 'duration_label'])
        );
        return $this->success($courses);
    }

    public function analytics($id)
    {
        $course = Course::with(['batches.enrollments', 'subjects'])->findOrFail($id);

        $batchIds = $course->batches->pluck('id');
        $enrollments = \Modules\Enrollment\app\Models\Enrollment::whereIn('batch_id', $batchIds)->get();

        $totalStudents = $enrollments->unique('student_id')->count();
        $activeStudents = $enrollments->where('status', 'active')->unique('student_id')->count();
        $totalRevenue = $enrollments->sum('paid_amount');
        $totalBatches = $course->batches->count();
        $openBatches = $course->batches->where('status', 'open')->count();
        $fullBatches = $course->batches->where('status', 'full')->count();

        $avgOccupancy = $totalBatches > 0
            ? round($course->batches->avg(function ($b) {
                return $b->capacity > 0 ? ($b->enrolled_count / $b->capacity) * 100 : 0;
            })) : 0;

        // Monthly enrollment trend (last 6 months)
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $count = \Modules\Enrollment\app\Models\Enrollment::whereIn('batch_id', $batchIds)
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();
            $rev = \Modules\Enrollment\app\Models\Enrollment::whereIn('batch_id', $batchIds)
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->sum('paid_amount');
            $trend[] = [
                'month' => $month->format('M'),
                'enrollments' => $count,
                'revenue' => (int) $rev,
            ];
        }

        return $this->success([
            'total_students' => $totalStudents,
            'active_students' => $activeStudents,
            'total_batches' => $totalBatches,
            'open_batches' => $openBatches,
            'full_batches' => $fullBatches,
            'total_revenue' => $totalRevenue,
            'avg_occupancy' => $avgOccupancy,
            'trend' => $trend,
        ]);
    }

    public function byCategory(Request $request, $category)
    {
        $filters = $request->only(['class_id', 'group_id', 'target']);
        $courses = $this->enrollmentService->getCoursesByCategory($category, $filters);
        return $this->success($courses);
    }

    /**
     * Course statistics for dashboard cards.
     */
    public function statistics()
    {
        $data = \Illuminate\Support\Facades\Cache::remember('courses.statistics', 300, function () {
            $total = Course::count();
            $active = Course::where('status', 'active')->count();
        $inactive = Course::where('status', 'inactive')->count();
        $totalStudents = \Modules\Enrollment\app\Models\Enrollment::whereIn('status', ['active', 'pending'])
            ->whereHas('batch.course')
            ->count();
        $totalBatches = \Modules\Enrollment\app\Models\Batch::count();

        $byCategory = Course::selectRaw('category, count(*) as count')
                ->groupBy('category')->get();

            return [
                'total_courses' => $total,
                'active_courses' => $active,
                'inactive_courses' => $inactive,
                'total_students' => $totalStudents,
                'total_batches' => $totalBatches,
                'by_category' => $byCategory,
            ];
        });

        return $this->success($data);
    }

    /**
     * Bulk action: delete, activate, archive multiple courses.
     */
    public function bulkAction(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:courses,id',
            'action' => 'required|in:delete,activate,archive',
        ]);

        $count = 0;
        foreach ($validated['ids'] as $id) {
            $course = Course::withTrashed()->find($id);
            if (!$course) continue;

            switch ($validated['action']) {
                case 'delete':
                    $course->delete();
                    break;
                case 'activate':
                    $course->update(['status' => 'active']);
                    break;
                case 'archive':
                    $course->update(['status' => 'inactive']);
                    break;
            }
            $count++;
        }

        return $this->success(compact('count'), "{$count} courses {$validated['action']}d");
    }

    /**
     * Export courses as CSV.
     */
    public function export(Request $request)
    {
        $courses = Course::with(['class', 'group', 'subjects'])->orderBy('sort_order')->get();

        $headers = ['Name', 'Code', 'Category', 'Class', 'Group', 'Duration', 'Status', 'Subjects', 'Batches', 'Created'];
        $rows = [];

        foreach ($courses as $c) {
            $rows[] = [
                $c->name,
                $c->code,
                $c->category,
                $c->class?->name ?? '',
                $c->group?->name ?? $c->target ?? '',
                $c->duration_label ?? '',
                $c->status,
                $c->subjects->pluck('name')->join('|'),
                $c->batches->count(),
                $c->created_at->format('Y-m-d'),
            ];
        }

        $csv = implode(',', $headers) . "\n";
        foreach ($rows as $row) {
            $csv .= '"' . implode('","', $row) . '"' . "\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="courses-export.csv"',
        ]);
    }

    /**
     * Restore a soft-deleted course.
     */
    public function restore($id)
    {
        $course = Course::withTrashed()->findOrFail($id);
        $course->restore();
        return $this->success($course, 'Course restored');
    }

    /**
     * Duplicate a course with new code.
     */
    public function duplicate($id)
    {
        $original = Course::with('subjects')->findOrFail($id);
        $new = $original->replicate();
        $new->name = $original->name . ' (Copy)';
        $new->slug = \Illuminate\Support\Str::slug($new->name) . '-' . \Illuminate\Support\Str::random(4);
        $new->code = 'CRS-' . strtoupper(\Illuminate\Support\Str::random(6));
        $new->is_featured = false;
        $new->save();

        // Duplicate subjects
        $subjectData = [];
        foreach ($original->subjects as $s) {
            $subjectData[$s->id] = [
                'fee' => $s->pivot->fee ?? 0,
                'is_optional' => $s->pivot->is_optional ?? false,
                'is_mandatory' => $s->pivot->is_mandatory ?? true,
                'sort_order' => $s->pivot->sort_order ?? 0,
            ];
        }
        if (!empty($subjectData)) {
            $new->subjects()->sync($subjectData);
        }

        return $this->created($new->load('subjects'), 'Course duplicated');
    }
}
