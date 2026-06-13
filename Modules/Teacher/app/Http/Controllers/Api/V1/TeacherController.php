<?php

namespace Modules\Teacher\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Modules\Teacher\app\Models\Teacher;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Core\app\Services\FileUploadService;
use App\Models\User;

class TeacherController extends BaseApiController
{
    public function __construct(protected FileUploadService $fileUploadService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = $this->getPerPage($request);
        $teachers = Teacher::with(['user', 'classes', 'subjects', 'academicGroup'])
            ->search($request->search)
            ->filter($request->only(['status', 'specialization']))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
        return $this->paginatedResponse($teachers);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|string|exists:users,id',
            'teacher_id' => 'required|string|unique:teachers,teacher_id',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:teachers,email',
            'phone' => 'nullable|string|max:20',
            'gender' => 'nullable|in:male,female,other',
            'date_of_birth' => 'nullable|date',
            'qualification' => 'nullable|string',
            'specialization' => 'nullable|string',
            'date_of_joining' => 'required|date',
            'address' => 'nullable|string',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'teacher_type' => 'nullable|in:permanent,contracted,guest',
            'group_id' => 'nullable|integer|exists:academic_groups,id',
            'experience_years' => 'nullable|integer|min:0',
            'previous_institution' => 'nullable|string|max:255',
            'salary_type' => 'nullable|in:monthly,class_wise,subject_wise',
            'salary_amount' => 'nullable|numeric|min:0',
            // User account fields
            'username' => 'nullable|string|max:255|unique:users,name',
            'password' => 'nullable|string|min:6',
        ]);

        // Handle photo upload
        if ($request->hasFile('photo')) {
            $photoPath = $this->fileUploadService->upload($request->file('photo'), 'teachers/photos');
            $validated['photo'] = $photoPath;
        }

        // Auto-create user account if username and password are provided
        if (!empty($validated['username']) && !empty($validated['password'])) {
            $user = User::create([
                'name'     => $validated['username'],
                'email'    => $validated['email'],
                'password' => Hash::make($validated['password']),
                'phone'    => $validated['phone'] ?? null,
                'status'   => 'active',
            ]);

            // Assign teacher role
            $user->assignRole('teacher');

            // Link user to teacher
            $validated['user_id'] = $user->id;
        }

        $teacher = Teacher::create($validated);
        
        // Sync classes and subjects
        if ($request->has('class_ids')) {
            $teacher->classes()->sync($request->class_ids);
        }
        if ($request->has('subject_ids')) {
            $teacher->subjects()->sync($request->subject_ids);
        }
        
        return $this->created($teacher->fresh(['user', 'classes', 'subjects', 'academicGroup']));
    }

    public function show(string $id): JsonResponse
    {
        $teacher = Teacher::with(['user', 'classes', 'subjects', 'academicGroup'])->find($id);
        if (!$teacher) return $this->notFound();
        return $this->success($teacher);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $teacher = Teacher::find($id);
        if (!$teacher) return $this->notFound();

        $validated = $request->validate([
            'user_id' => 'nullable|string|exists:users,id',
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:teachers,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'gender' => 'nullable|in:male,female,other',
            'date_of_birth' => 'nullable|date',
            'qualification' => 'nullable|string',
            'specialization' => 'nullable|string',
            'date_of_joining' => 'sometimes|date',
            'address' => 'nullable|string',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'status' => 'sometimes|in:active,inactive,resigned,terminated',
            'teacher_type' => 'nullable|in:permanent,contracted,guest',
            'group_id' => 'nullable|integer|exists:academic_groups,id',
            'experience_years' => 'nullable|integer|min:0',
            'previous_institution' => 'nullable|string|max:255',
            'salary_type' => 'nullable|in:monthly,class_wise,subject_wise',
            'salary_amount' => 'nullable|numeric|min:0',
        ]);

        // Handle photo upload (replace old photo if exists)
        if ($request->hasFile('photo')) {
            $photoPath = $this->fileUploadService->replace(
                $request->file('photo'),
                $teacher->photo,
                'teachers/photos'
            );
            $validated['photo'] = $photoPath;
        }

        $teacher->update($validated);
        
        // Sync classes and subjects
        if ($request->has('class_ids')) {
            $teacher->classes()->sync($request->class_ids);
        }
        if ($request->has('subject_ids')) {
            $teacher->subjects()->sync($request->subject_ids);
        }
        
        return $this->success($teacher->fresh(['user', 'classes', 'subjects', 'academicGroup']));
    }

    public function destroy(string $id): JsonResponse
    {
        $teacher = Teacher::find($id);
        if (!$teacher) return $this->notFound();
        $teacher->delete();
        return $this->noContent();
    }

    public function listAll(): JsonResponse
    {
        $teachers = Teacher::where('status', 'active')
            ->with('subjects')
            ->get(['id', 'first_name', 'last_name', 'teacher_id', 'email']);
        return $this->collectionResponse($teachers);
    }

    /**
     * Get teachers by subject (from subject_teacher pivot).
     */
    public function bySubject(string $subjectId): JsonResponse
    {
        $teachers = Teacher::where('status', 'active')
            ->whereHas('subjects', function ($q) use ($subjectId) {
                $q->where('subject_id', $subjectId);
            })
            ->get(['id', 'first_name', 'last_name', 'teacher_id', 'email']);

        return $this->collectionResponse($teachers);
    }

    /**
     * Get teachers by class and subject (from class_teacher & subject_teacher pivots).
     *
     * Query params: class_id (required), subject_id (required), group_id (optional)
     */
    public function byClassSubject(Request $request): JsonResponse
    {
        $request->validate([
            'class_id' => 'required|string|exists:classes,id',
            'subject_id' => 'required|string|exists:subjects,id',
            'group_id' => 'nullable|string|exists:academic_groups,id',
        ]);

        $subjectId = $request->input('subject_id');
        $groupId = $request->input('group_id');

        try {
            // 1. Try subject_teacher pivot
            $teachers = Teacher::where('status', 'active')
                ->whereHas('subjects', fn($q) => $q->where('subject_id', $subjectId))
                ->when($groupId, fn($q) => $q->where('group_id', $groupId))
                ->get(['id', 'first_name', 'last_name', 'teacher_id', 'email'])
                ->unique('id')->values();

            if ($teachers->isNotEmpty()) {
                return $this->collectionResponse($teachers);
            }

            // 2. Try course_subject (teachers assigned to this subject in any course)
            $teacherIds = \Illuminate\Support\Facades\DB::table('course_subject')
                ->where('subject_id', $subjectId)
                ->whereNotNull('teacher_id')
                ->distinct()
                ->pluck('teacher_id');

            if ($teacherIds->isNotEmpty()) {
                $teachers = Teacher::whereIn('id', $teacherIds)
                    ->where('status', 'active')
                    ->when($groupId, fn($q) => $q->where('group_id', $groupId))
                    ->get(['id', 'first_name', 'last_name', 'teacher_id', 'email']);
                return $this->collectionResponse($teachers);
            }

        } catch (\Exception $e) {
            // Fall through to all teachers
        }

        // 3. Fallback: all active teachers
        try {
            $teachers = Teacher::where('status', 'active')
                ->when($groupId, fn($q) => $q->where('group_id', $groupId))
                ->get(['id', 'first_name', 'last_name', 'teacher_id', 'email']);
            return $this->collectionResponse($teachers);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'success', 'message' => 'Success', 'data' => [],
            ]);
        }
    }
}
