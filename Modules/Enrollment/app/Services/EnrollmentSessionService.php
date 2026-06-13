<?php

namespace Modules\Enrollment\app\Services;

use Illuminate\Support\Facades\DB;
use Modules\Enrollment\app\Models\EnrollmentSession;
use Modules\Enrollment\app\Models\Enrollment;
use Modules\Student\app\Models\Student;
use Modules\Student\app\Models\Guardian;

class EnrollmentSessionService
{
    public function __construct(
        protected EnrollmentService $enrollmentService,
        protected PaymentService $paymentService,
        protected DocumentService $documentService,
        protected ActivityLogService $activityLogService,
    ) {}

    public function initiate(string $type, ?string $studentId = null): EnrollmentSession
    {
        // Steps: 0=type, 1=student, 2=academic, 3=course, 4=docs, 5=payment
        if ($studentId) {
            // Existing student: skip student-info step (step 1), start at academic (step 2)
            $student = Student::with('guardian')->find($studentId);
            return EnrollmentSession::create([
                'enrollment_type' => $type,
                'student_id' => $studentId,
                'current_step' => 2,
                'step_data' => ['student_info' => $student?->toArray() ?? []],
                'status' => 'in_progress',
                'expires_at' => now()->addHours(24),
                'created_by' => auth()->id(),
            ]);
        }

        return EnrollmentSession::create([
            'enrollment_type' => $type,
            'student_id' => null,
            'current_step' => 1, // start at student-info step
            'step_data' => [],
            'status' => 'in_progress',
            'expires_at' => now()->addHours(24),
            'created_by' => auth()->id(),
        ]);
    }

    public function getSession(string $sessionId): EnrollmentSession
    {
        return EnrollmentSession::with('student.guardian')->findOrFail($sessionId);
    }

    public function saveStepData(EnrollmentSession $session, string $step, array $data): EnrollmentSession
    {
        $stepData = $session->step_data ?? [];
        $stepData[$step] = $data;
        $session->update(['step_data' => $stepData]);
        return $session->fresh();
    }

    public function advanceStep(EnrollmentSession $session, int $nextStep): EnrollmentSession
    {
        $session->update(['current_step' => $nextStep]);
        return $session->fresh();
    }

    /**
     * Finalize enrollment: create student + guardian + enrollment + payment + log
     */
    public function finalize(EnrollmentSession $session, array $paymentData): array
    {
        return DB::transaction(function () use ($session, $paymentData) {
            $stepData = $session->step_data ?? [];
            $studentInfo = $stepData['student_info'] ?? [];
            $academicInfo = $stepData['academic_info'] ?? [];
            $courseBatch = $stepData['course_batch'] ?? [];
            $documents = $stepData['documents'] ?? [];

            // 1. Create or update student
            if ($session->enrollment_type === 'new') {
                $studentData = array_merge($studentInfo, $academicInfo);
                $studentData['student_id'] = $this->enrollmentService->generateStudentId();
                if (empty($studentData['academic_session_id'])) {
                    $sess = \Modules\Academic\app\Models\AcademicSession::where('is_current', true)->first();
                    $studentData['academic_session_id'] = $sess?->id;
                }
                $studentData['admission_date'] = now()->toDateString();
                $studentData['last_name'] = $studentData['last_name'] ?? '';
                $student = Student::create($studentData);

                // Create guardian if info provided
                if (!empty($studentInfo['guardian_name']) || !empty($studentInfo['guardian_phone'])) {
                    Guardian::create([
                        'student_id' => $student->id,
                        'guardian_name' => $studentInfo['guardian_name'] ?? null,
                        'guardian_phone' => $studentInfo['guardian_phone'] ?? null,
                        'guardian_relation' => $studentInfo['guardian_relation'] ?? null,
                        'guardian_email' => $studentInfo['guardian_email'] ?? null,
                        'father_name' => $studentInfo['father_name'] ?? null,
                        'father_phone' => $studentInfo['father_phone'] ?? null,
                        'mother_name' => $studentInfo['mother_name'] ?? null,
                        'mother_phone' => $studentInfo['mother_phone'] ?? null,
                    ]);
                }
            } else {
                $student = Student::findOrFail($session->student_id);
            }

            // 2. Check duplicate
            $courseId = $courseBatch['course_id'] ?? null;
            if ($courseId) {
                $duplicate = $this->enrollmentService->checkDuplicateEnrollment($student->id, $courseId);
                if ($duplicate) {
                    throw new \Exception('Student already enrolled in this course: ' . $duplicate->enrollment_no);
                }
            }

            // 3. Create enrollment
            $enrollment = $this->enrollmentService->enroll($student, [
                'course_id' => $courseBatch['course_id'] ?? null,
                'batch_id' => $courseBatch['batch_id'],
                'subject_ids' => $courseBatch['subject_ids'] ?? [],
                'subject_teachers' => $courseBatch['subject_teachers'] ?? [],
                'subject_batches' => $courseBatch['subject_batches'] ?? [],
                'paid_amount' => $paymentData['amount'] ?? 0,
                'enrollment_type' => $session->enrollment_type === 'existing' ? 'renewal' : 'new',
                'enrolled_class_id' => $academicInfo['current_class_id'] ?? null,
                'enrolled_group_id' => $academicInfo['group_id'] ?? null,
                'academic_session_id' => $academicInfo['academic_session_id'] ?? null,
                'guardian_phone' => $studentInfo['guardian_phone'] ?? $student->phone ?? null,
                'guardian_email' => $studentInfo['guardian_email'] ?? null,
            ]);

            // 4. Record payment
            $payment = $this->paymentService->recordPayment(
                $enrollment,
                $paymentData['amount'] ?? 0,
                $paymentData['payment_method'] ?? 'cash',
                $paymentData['reference'] ?? null,
                $paymentData['transaction_id'] ?? null
            );

            // 5. Move documents to permanent storage
            if (!empty($documents)) {
                $this->documentService->moveToPermanent($documents, $student->id, $enrollment->id);
            }

            // 6. Log activity
            $this->activityLogService->log($enrollment->id, 'created', 'Enrollment created via wizard', null, $enrollment->status);

            // 7. Mark session complete
            $session->update(['status' => 'completed']);

            return [
                'enrollment' => $enrollment->fresh()->load(['student', 'batch.course', 'subjects']),
                'payment' => $payment,
                'student' => $student->fresh(),
            ];
        });
    }

    public function abandon(EnrollmentSession $session): void
    {
        $session->update(['status' => 'expired']);
        // Clean up temp files
        $stepData = $session->step_data ?? [];
        if (!empty($stepData['documents'])) {
            $this->documentService->cleanTempFiles($stepData['documents']);
        }
    }
}
