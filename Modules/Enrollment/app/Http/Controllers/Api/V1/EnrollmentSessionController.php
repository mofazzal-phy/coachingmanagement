<?php

namespace Modules\Enrollment\app\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Enrollment\app\Models\EnrollmentSession;
use Modules\Enrollment\app\Services\EnrollmentSessionService;
use Modules\Enrollment\app\Services\DocumentService;

class EnrollmentSessionController extends BaseApiController
{
    public function __construct(
        protected EnrollmentSessionService $sessionService,
        protected DocumentService $documentService,
    ) {}

    /**
     * POST /enrollment/initiate — Start new session
     */
    public function initiate(Request $request)
    {
        $validated = $request->validate([
            'enrollment_type' => 'required|in:new,existing,import',
            'student_id' => 'required_if:enrollment_type,existing|exists:students,id',
        ]);

        $session = $this->sessionService->initiate(
            $validated['enrollment_type'],
            $validated['student_id'] ?? null
        );

        return $this->created($session, 'Enrollment session started');
    }

    /**
     * GET /enrollment/session/{sessionId}
     */
    public function show($sessionId)
    {
        $session = $this->sessionService->getSession($sessionId);
        return $this->success($session);
    }

    /**
     * POST /enrollment/session/{sessionId}/student-info
     */
    public function saveStudentInfo(Request $request, $sessionId)
    {
        $session = $this->sessionService->getSession($sessionId);

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'blood_group' => 'nullable|string|max:5',
            'religion' => 'nullable|string|max:50',
            'photo' => 'nullable|string',
            'guardian_name' => 'required|string|max:255',
            'guardian_phone' => 'required|string|max:20',
            'guardian_relation' => 'nullable|string|max:100',
            'guardian_email' => 'nullable|email',
            'father_name' => 'nullable|string|max:255',
            'father_phone' => 'nullable|string|max:20',
            'mother_name' => 'nullable|string|max:255',
            'mother_phone' => 'nullable|string|max:20',
            'present_address' => 'nullable|string|max:500',
            'permanent_address' => 'nullable|string|max:500',
        ]);

        $session = $this->sessionService->saveStepData($session, 'student_info', $validated);
        $session = $this->sessionService->advanceStep($session, 2);

        return $this->success($session, 'Student info saved');
    }

    /**
     * POST /enrollment/session/{sessionId}/academic-info
     */
    public function saveAcademicInfo(Request $request, $sessionId)
    {
        $session = $this->sessionService->getSession($sessionId);

        $validated = $request->validate([
            'current_class_id' => 'nullable|exists:classes,id',
            'current_section_id' => 'nullable|exists:sections,id',
            'group_id' => 'nullable|exists:academic_groups,id',
            'academic_session_id' => 'nullable|exists:academic_sessions,id',
            'previous_school' => 'nullable|string|max:255',
            'previous_class' => 'nullable|string|max:100',
            'ssc_result' => 'nullable|numeric|min:0|max:5.00',
            'roll_no' => 'nullable|string|max:20',
        ]);

        $session = $this->sessionService->saveStepData($session, 'academic_info', $validated);
        $session = $this->sessionService->advanceStep($session, 3);

        return $this->success($session, 'Academic info saved');
    }

    /**
     * POST /enrollment/session/{sessionId}/course-batch
     */
    public function saveCourseBatch(Request $request, $sessionId)
    {
        $session = $this->sessionService->getSession($sessionId);

        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'batch_id' => 'required|exists:batches,id',
            'subject_ids' => 'nullable|array',
            'subject_ids.*' => 'exists:subjects,id',
        ]);

        $session = $this->sessionService->saveStepData($session, 'course_batch', $validated);
        $session = $this->sessionService->advanceStep($session, 4);

        return $this->success($session, 'Course & batch selected');
    }

    /**
     * POST /enrollment/session/{sessionId}/documents
     */
    public function uploadDocuments(Request $request, $sessionId)
    {
        $session = $this->sessionService->getSession($sessionId);

        $request->validate([
            'documents' => 'required|array|max:5',
            'documents.*.type' => 'required|in:photo,birth_certificate,marksheet,nid,other',
            'documents.*.file' => 'required|file|max:5120|mimes:jpeg,png,jpg,pdf',
        ]);

        $uploadedDocs = [];
        foreach ($request->file('documents') as $index => $file) {
            $type = $request->input("documents.{$index}.type", 'other');
            $info = $this->documentService->uploadTemp($file, $sessionId);
            $info['type'] = $type;
            $uploadedDocs[] = $info;
        }

        // Merge with existing docs
        $existingDocs = $session->step_data['documents'] ?? [];
        $allDocs = array_merge($existingDocs, $uploadedDocs);

        $session = $this->sessionService->saveStepData($session, 'documents', $allDocs);
        $session = $this->sessionService->advanceStep($session, 5);

        return $this->success($session, 'Documents uploaded');
    }

    /**
     * POST /enrollment/session/{sessionId}/review
     */
    public function markReady(Request $request, $sessionId)
    {
        $session = $this->sessionService->getSession($sessionId);
        $request->validate(['confirmed' => 'required|boolean|accepted']);

        $session = $this->sessionService->advanceStep($session, 6);

        return $this->success($session, 'Ready for payment');
    }

    /**
     * POST /enrollment/session/{sessionId}/payment — Finalize
     */
    public function finalizePayment(Request $request, $sessionId)
    {
        $session = $this->sessionService->getSession($sessionId);

        $validated = $request->validate([
            'payment_method' => 'required|in:cash,bkash,nagad,rocket,bank,card',
            'amount' => 'required|numeric|min:0',
            'transaction_id' => 'nullable|string|max:100',
            'reference' => 'nullable|string|max:100',
            'payment_date' => 'nullable|date',
        ]);

        try {
            $result = $this->sessionService->finalize($session, $validated);
            return $this->created($result, 'Enrollment confirmed!');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * DELETE /enrollment/session/{sessionId}
     */
    public function abandon($sessionId)
    {
        $session = $this->sessionService->getSession($sessionId);
        $this->sessionService->abandon($session);
        return $this->noContent('Session abandoned');
    }
}
