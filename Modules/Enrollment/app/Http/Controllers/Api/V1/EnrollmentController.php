<?php

namespace Modules\Enrollment\app\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Enrollment\app\Models\Enrollment;
use Modules\Enrollment\app\Models\Batch;
use Modules\Enrollment\app\Services\EnrollmentService;
use Modules\Student\app\Models\Student;

class EnrollmentController extends BaseApiController
{
    protected $enrollmentService;

    public function __construct(EnrollmentService $enrollmentService)
    {
        $this->enrollmentService = $enrollmentService;
    }

    public function index(Request $request)
    {
        $query = Enrollment::with([
            'student:id,first_name,last_name,student_id,phone',
            'batch:id,name,code,mode,course_id',
            'batch.course:id,name,category',
            'subjects:id,name',
            'enrolledClass:id,name',
            'enrolledGroup:id,name',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('mode')) {
            $query->where('mode', $request->mode);
        }

        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->batch_id);
        }

        if ($request->filled('course_id')) {
            $query->whereHas('batch', function ($q) use ($request) {
                $q->where('course_id', $request->course_id);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('enrollment_no', 'like', "%{$search}%")
                    ->orWhere('guardian_phone', 'like', "%{$search}%")
                    ->orWhereHas('student', function ($sq) use ($search) {
                        $sq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('student_id', 'like', "%{$search}%");
                    });
            });
        }

        $enrollments = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 15);
        return $this->paginatedResponse($enrollments);
    }

    public function show($id)
    {
        $enrollment = Enrollment::with([
            'student.guardian',
            'batch.course.subjects',
            'batch.room',
            'batch.teacher',
            'subjects',
            'academicSession',
            'enrolledClass',
            'enrolledGroup',
            'previousEnrollment',
        ])->findOrFail($id);

        return $this->success($enrollment);
    }

    public function enroll(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'batch_id' => 'required|exists:batches,id',
            'course_id' => 'nullable|exists:courses,id',
            'enrollment_type' => 'in:new,renewal',
            'fee_type' => 'nullable|in:one_time,monthly',
            'subject_ids' => 'nullable|array',
            'subject_ids.*' => 'exists:subjects,id',
            'subject_teachers' => 'nullable|array',
            'subject_batches' => 'nullable|array',
            'paid_amount' => 'numeric|min:0',
            'payment_method' => 'nullable|string|max:50',
            'payment_reference' => 'nullable|string|max:100',
            'payment_transaction_id' => 'nullable|string|max:100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'guardian_phone' => 'nullable|string|max:20',
            'guardian_email' => 'nullable|email|max:255',
            'academic_session_id' => 'nullable|exists:academic_sessions,id',
            'enrolled_class_id' => 'nullable|exists:classes,id',
            'enrolled_group_id' => 'nullable|exists:academic_groups,id',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|min:6|max:255',
            'discount_id' => 'nullable|exists:discount_rules,id',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        try {
            $student = Student::findOrFail($validated['student_id']);

            if (($validated['enrollment_type'] ?? 'new') === 'renewal') {
                $enrollment = $this->enrollmentService->renew($student, $validated);
            } else {
                $enrollment = $this->enrollmentService->enroll($student, $validated);
            }

            return $this->created($enrollment, 'Enrollment created successfully!');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function renew(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'batch_id' => 'required|exists:batches,id',
            'subject_ids' => 'nullable|array',
            'subject_ids.*' => 'exists:subjects,id',
            'paid_amount' => 'numeric|min:0',
            'guardian_phone' => 'nullable|string|max:20',
            'guardian_email' => 'nullable|email|max:255',
            'discount_id' => 'nullable|exists:discount_rules,id',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        try {
            $student = Student::findOrFail($validated['student_id']);
            $enrollment = $this->enrollmentService->renew($student, $validated);

            return $this->created($enrollment, 'Renewal successful!');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function changeBatch(Request $request, $id)
    {
        $validated = $request->validate([
            'batch_id' => 'required|exists:batches,id',
        ]);

        try {
            $enrollment = Enrollment::findOrFail($id);
            $enrollment = $this->enrollmentService->transferBatch($enrollment, $validated['batch_id']);

            return $this->success($enrollment, 'Batch changed successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function recordPayment(Request $request, $id)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'method' => 'nullable|string|max:50',
            'reference' => 'nullable|string|max:100',
            'transaction_id' => 'nullable|string|max:100',
        ]);

        try {
            $enrollment = Enrollment::findOrFail($id);
            $enrollment = $this->enrollmentService->recordPayment(
                $enrollment,
                $validated['amount'],
                $validated['method'] ?? null,
                $validated['reference'] ?? null,
                $validated['transaction_id'] ?? null
            );

            $message = $enrollment->status === 'active'
                ? 'Payment recorded & enrollment confirmed!'
                : 'Payment recorded successfully';

            return $this->success($enrollment, $message);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Manually confirm a pending enrollment.
     */
    public function confirmEnrollment($id)
    {
        try {
            $enrollment = Enrollment::with('student')->findOrFail($id);
            $enrollment = $this->enrollmentService->confirmEnrollment($enrollment);

            // For public/self enrollments, activate (or create) the student's login
            // account on approval so they can access the portal. Cash applicants already
            // have an inactive account from submission — approval flips it to active.
            $credentials = null;
            if ($enrollment->source === 'public' && $enrollment->student) {
                $credentials = $this->enrollmentService->provisionStudentLogin($enrollment->student);

                if (!empty($credentials['created'])) {
                    // A fresh account was created — email the generated password.
                    $this->emailCredentials($enrollment, $credentials);
                } elseif (!empty($credentials['activated'])) {
                    // Existing (self-chosen) credentials — notify that login is now open.
                    $this->emailAccountActivated($enrollment, $credentials);
                }
            }

            return $this->success([
                'enrollment' => $enrollment->fresh(),
                'credentials' => $credentials,
            ], 'Enrollment confirmed successfully!');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Best-effort email of login credentials to a newly approved student.
     */
    private function emailCredentials(Enrollment $enrollment, array $credentials): void
    {
        $email = $enrollment->student?->email ?: $enrollment->guardian_email;
        if (!$email || empty($credentials['password'])) {
            return;
        }

        try {
            $name = trim(($enrollment->student->first_name ?? '') . ' ' . ($enrollment->student->last_name ?? ''));
            $username = $credentials['username'];
            $password = $credentials['password'];
            $institute = config('app.name', 'Poralekha');

            \Illuminate\Support\Facades\Mail::raw(
                "Hello {$name},\n\n" .
                "Your admission ({$enrollment->enrollment_no}) has been confirmed.\n\n" .
                "You can now log in to the student portal:\n" .
                "Username: {$username}\n" .
                "Password: {$password}\n\n" .
                "Please change your password after first login.\n\n" .
                "— {$institute}",
                function ($message) use ($email, $institute) {
                    $message->to($email)->subject("{$institute} — Your student portal login");
                }
            );
        } catch (\Throwable $e) {
            // Don't fail the approval if mail isn't configured.
            \Illuminate\Support\Facades\Log::warning('Credential email failed: ' . $e->getMessage());
        }
    }

    /**
     * Notify a student that their (self-chosen) login is now active after approval.
     */
    private function emailAccountActivated(Enrollment $enrollment, array $credentials): void
    {
        $email = $enrollment->student?->email ?: $enrollment->guardian_email;
        if (!$email) {
            return;
        }

        try {
            $name = trim(($enrollment->student->first_name ?? '') . ' ' . ($enrollment->student->last_name ?? ''));
            $username = $credentials['username'] ?? '';
            $institute = config('app.name', 'Poralekha');

            \Illuminate\Support\Facades\Mail::raw(
                "Hello {$name},\n\n" .
                "Good news! Your admission ({$enrollment->enrollment_no}) has been approved.\n\n" .
                "Your student portal login is now active.\n" .
                "Username: {$username}\n" .
                "Password: (the password you chose during enrollment)\n\n" .
                "— {$institute}",
                function ($message) use ($email, $institute) {
                    $message->to($email)->subject("{$institute} — Your enrollment is approved");
                }
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Activation email failed: ' . $e->getMessage());
        }
    }

    public function dropout(Request $request, $id)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $enrollment = Enrollment::findOrFail($id);
            $enrollment = $this->enrollmentService->dropout($enrollment, $validated['reason'] ?? null);

            return $this->success($enrollment, 'Student dropped from enrollment');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function calculateFee(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'subject_ids' => 'nullable|array',
            'subject_ids.*' => 'exists:subjects,id',
            'student_id' => 'nullable|exists:students,id',
            'fee_type' => 'nullable|in:one_time,monthly',
            'discount_id' => 'nullable|exists:discount_rules,id',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        $course = \Modules\Enrollment\app\Models\Course::findOrFail($validated['course_id']);
        $student = null;

        if (!empty($validated['student_id'])) {
            $student = Student::find($validated['student_id']);
        }

        $feeCalculation = $this->enrollmentService->calculateFee(
            $course,
            $validated['subject_ids'] ?? [],
            $student,
            $validated['fee_type'] ?? 'one_time',
            $validated['discount_id'] ?? null,
            $validated['discount_percent'] ?? null
        );

        return $this->success($feeCalculation);
    }

    /**
     * Search student by phone/name/ID for quick enrollment.
     */
    public function searchStudent(Request $request)
    {
        $request->validate(['q' => 'required|string|min:2']);
        $results = $this->enrollmentService->searchStudent($request->q);
        return $this->success($results);
    }

    /**
     * Get suggested courses based on student's class/group/target.
     */
    public function suggestedCourses(Request $request)
    {
        $courses = $this->enrollmentService->getSuggestedCourses(
            $request->class_id,
            $request->group_id ? (int) $request->group_id : null,
            $request->target
        );
        return $this->success($courses);
    }

    /**
     * Get suggested batches for a course.
     */
    public function suggestedBatches(Request $request)
    {
        $request->validate(['course_id' => 'required|exists:courses,id']);
        $batches = $this->enrollmentService->getSuggestedBatches(
            $request->course_id,
            $request->mode
        );
        return $this->success($batches);
    }

    /**
     * Check sibling discount for a student.
     */
    public function checkSiblingDiscount($studentId)
    {
        try {
            $student = Student::findOrFail($studentId);
            $discount = $this->enrollmentService->checkSiblingDiscount($student);
            return $this->success($discount);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Get pending enrollments that need payment.
     */
    public function pendingPayments(Request $request)
    {
        $filters = $request->only(['batch_id', 'course_id', 'search', 'per_page']);
        $enrollments = $this->enrollmentService->getPendingEnrollments($filters);
        return $this->paginatedResponse($enrollments);
    }

    /**
     * Check for duplicate enrollment.
     */
    public function checkDuplicate(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'course_id' => 'required|exists:courses,id',
        ]);

        $duplicate = $this->enrollmentService->checkDuplicateEnrollment(
            $request->student_id,
            $request->course_id
        );

        return $this->success([
            'is_duplicate' => !is_null($duplicate),
            'existing_enrollment' => $duplicate,
        ]);
    }

    /**
     * Generate a new student ID.
     */
    public function generateStudentId()
    {
        return $this->success([
            'student_id' => $this->enrollmentService->generateStudentId(),
        ]);
    }

    /**
     * Add to waiting list.
     */
    public function addToWaitingList(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'batch_id' => 'required|exists:batches,id',
            'remarks' => 'nullable|string|max:500',
        ]);

        try {
            $result = $this->enrollmentService->addToWaitingList(
                $request->student_id,
                $request->batch_id,
                $request->remarks ?? null
            );
            return $this->created($result, 'Added to waiting list');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Approve from waiting list.
     */
    public function approveFromWaitingList($id)
    {
        try {
            $enrollment = $this->enrollmentService->approveFromWaitingList($id);
            return $this->success($enrollment, 'Student moved from waiting list');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Get waiting list.
     */
    public function waitingList(Request $request)
    {
        $query = Enrollment::with(['student:id,first_name,last_name,student_id,phone', 'batch.course'])
            ->where('status', 'waiting')
            ->orderByRaw("FIELD(priority,'urgent','high','normal')")
            ->orderBy('waiting_position');

        if ($request->filled('batch_id')) $query->where('batch_id', $request->batch_id);
        if ($request->filled('search')) {
            $q = $request->search;
            $query->whereHas('student', fn($s) => $s->where('first_name','like',"%{$q}%")->orWhere('last_name','like',"%{$q}%")->orWhere('phone','like',"%{$q}%"));
        }

        $enrollments = $query->paginate($request->per_page ?? 20);
        return $this->paginatedResponse($enrollments);
    }

    /**
     * Bulk approve waiting list enrollments.
     */
    public function bulkApproveWaitingList(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:enrollments,id',
        ]);

        $count = 0;
        foreach ($validated['ids'] as $id) {
            try {
                $this->enrollmentService->approveFromWaitingList($id);
                $count++;
            } catch (\Exception $e) {}
        }
        return $this->success(compact('count'), "{$count} students approved from waiting list");
    }

    /**
     * Waiting list statistics.
     */
    public function waitingListStats()
    {
        $total = Enrollment::where('status', 'waiting')->count();
        $byPriority = Enrollment::selectRaw('priority, count(*) as count')
            ->where('status', 'waiting')->groupBy('priority')->get();
        $byBatch = Enrollment::selectRaw('batch_id, count(*) as count')
            ->where('status', 'waiting')->groupBy('batch_id')
            ->with('batch:id,name')->orderByDesc('count')->limit(10)->get();

        return $this->success([
            'total_waiting' => $total,
            'by_priority' => $byPriority,
            'by_batch' => $byBatch,
        ]);
    }

    /**
     * Get receipt/invoice data for an enrollment.
     */
    public function receipt($id)
    {
        $enrollment = Enrollment::with([
            'student:id,first_name,last_name,student_id,phone',
            'student.guardian',
            'batch.course',
            'batch.teacher',
            'subjects',
            'payments' => function ($q) { $q->orderBy('created_at', 'desc'); },
        ])->findOrFail($id);

        return $this->success($enrollment);
    }

    /**
     * Get all audit logs with filters.
     */
    public function auditLogs(Request $request)
    {
        $service = app(\Modules\Enrollment\app\Services\ActivityLogService::class);
        $filters = $request->only(['model_type', 'action', 'user_id']);
        $logs = $service->getAllLogs($filters, $request->per_page ?? 30);
        return $this->paginatedResponse($logs);
    }

    /**
     * Get activity timeline for an enrollment.
     */
    public function timeline($id)
    {
        $service = app(\Modules\Enrollment\app\Services\ActivityLogService::class);
        return $this->success($service->getTimeline($id));
    }

    /**
     * Refund a payment for an enrollment.
     */
    public function refund($id)
    {
        $enrollment = Enrollment::with('payments')->findOrFail($id);
        $result = app(\Modules\Enrollment\app\Services\PaymentService::class)->refundPayment($enrollment);
        if (!$result) return $this->error('No payment to refund', 422);
        return $this->success($result, 'Payment refunded');
    }

    /**
     * Verify a pending payment (admin confirms).
     */
    public function verifyPayment($id)
    {
        $enrollment = Enrollment::with('payments')->findOrFail($id);
        $payment = $enrollment->payments->last();
        if (!$payment) return $this->error('No payment found', 422);
        if ($payment->payment_status === 'paid') return $this->error('Already verified', 422);

        $payment->update([
            'payment_status' => 'paid',
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);
        $enrollment->update(['payment_status' => 'paid', 'status' => 'active']);

        return $this->success($payment, 'Payment verified');
    }

    /**
     * Download invoice/receipt as printable HTML.
     */
    public function invoiceDownload($id)
    {
        $enrollment = Enrollment::with(['student:id,first_name,last_name,student_id,phone', 'batch.course', 'payments'])->findOrFail($id);
        $payment = $enrollment->payments->last();
        if (!$payment) return $this->error('No payment found', 404);

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Receipt '.$enrollment->enrollment_no.'</title>
        <style>body{font-family:Arial,sans-serif;max-width:700px;margin:30px auto;padding:20px;color:#333}
        .header{text-align:center;border-bottom:2px solid #333;padding-bottom:15px;margin-bottom:20px}
        .header h2{margin:0;font-size:20px}.header p{margin:5px 0;font-size:13px;color:#666}
        .title{text-align:center;font-size:22px;font-weight:bold;margin:15px 0}
        .row{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #eee;font-size:14px}
        .row strong{color:#111}.total{font-size:18px;font-weight:bold;margin-top:10px;text-align:right}
        .footer{text-align:center;margin-top:30px;font-size:12px;color:#999;border-top:1px solid #eee;padding-top:15px}
        @media print{body{margin:0;padding:15px}}</style></head><body>
        <div class="header"><h2>COACHING MANAGEMENT SYSTEM</h2><p>123 Mirpur Road, Dhaka-1216 | +880 1700-000000</p></div>
        <div class="title">MONEY RECEIPT</div>
        <div class="row"><span>Receipt No:</span><strong>'.$payment->receipt_no.'</strong></div>
        <div class="row"><span>Date:</span><span>'.$payment->payment_date.'</span></div>
        <div class="row"><span>Student:</span><strong>'.($enrollment->student->first_name??'').' '.($enrollment->student->last_name??'').'</strong></div>
        <div class="row"><span>Student ID:</span><span>'.($enrollment->student->student_id??'N/A').'</span></div>
        <div class="row"><span>Enrollment No:</span><span>'.$enrollment->enrollment_no.'</span></div>
        <div class="row"><span>Course:</span><span>'.($enrollment->batch->course->name??'N/A').'</span></div>
        <div class="row"><span>Batch:</span><span>'.($enrollment->batch->name??'N/A').' ('.($enrollment->batch->mode??'').')</span></div>
        <div class="row"><span>Payment Method:</span><span>'.ucfirst($payment->payment_method).'</span></div>
        <div class="total">৳'.number_format($payment->amount, 2).'</div>
        <p style="text-align:right;font-style:italic;margin-top:5px">'.ucwords($this->numberToWords($payment->amount)).' Taka Only</p>
        <div class="footer"><p>Thank you for your payment!</p><p>This is a computer-generated receipt.</p></div>
        </body></html>';

        return response($html, 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => 'attachment; filename="Receipt-'.$enrollment->enrollment_no.'.html"',
        ]);
    }

    /**
     * Update an enrollment (edit).
     */
    public function update(Request $request, $id)
    {
        $enrollment = Enrollment::findOrFail($id);

        $validated = $request->validate([
            'batch_id' => 'sometimes|exists:batches,id',
            'academic_session_id' => 'nullable|exists:academic_sessions,id',
            'enrolled_class_id' => 'nullable|exists:classes,id',
            'enrolled_group_id' => 'nullable|exists:academic_groups,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'guardian_phone' => 'nullable|string|max:20',
            'guardian_email' => 'nullable|email|max:255',
            'subject_ids' => 'nullable|array',
            'subject_ids.*' => 'exists:subjects,id',
        ]);

        try {
            if (!empty($validated['batch_id']) && $validated['batch_id'] !== $enrollment->batch_id) {
                $enrollment = $this->enrollmentService->transferBatch($enrollment, $validated['batch_id']);
            }

            $enrollment->update($validated);

            if (isset($validated['subject_ids'])) {
                $enrollment->subjects()->sync($validated['subject_ids']);
            }

            return $this->success($enrollment->fresh(['student', 'batch.course', 'subjects']), 'Enrollment updated');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Delete (archive) an enrollment.
     */
    public function destroy($id)
    {
        $enrollment = Enrollment::findOrFail($id);
        $enrollment->delete();

        return $this->noContent('Enrollment deleted');
    }

    /**
     * Export enrollments as Excel/CSV.
     */
    public function export(Request $request)
    {
        $query = Enrollment::with([
            'student:id,first_name,last_name,student_id,phone',
            'batch:id,name,code,mode,course_id',
            'batch.course:id,name,category',
        ]);

        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('payment_status')) $query->where('payment_status', $request->payment_status);
        if ($request->filled('mode')) $query->where('mode', $request->mode);
        if ($request->filled('batch_id')) $query->where('batch_id', $request->batch_id);
        if ($request->filled('course_id')) {
            $query->whereHas('batch', fn($q) => $q->where('course_id', $request->course_id));
        }

        $enrollments = $query->orderBy('created_at', 'desc')->get();

        $format = $request->input('format', 'csv');
        $filename = 'enrollments-export-' . now()->format('Y-m-d');

        if ($format === 'csv') {
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
            ];
            $callback = function () use ($enrollments) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['Enrollment No', 'Student Name', 'Student ID', 'Phone', 'Course', 'Batch', 'Mode', 'Total Fee', 'Paid', 'Due', 'Status', 'Payment', 'Date']);
                foreach ($enrollments as $e) {
                    fputcsv($handle, [
                        $e->enrollment_no,
                        ($e->student->first_name ?? '') . ' ' . ($e->student->last_name ?? ''),
                        $e->student->student_id ?? '',
                        $e->student->phone ?? '',
                        $e->batch->course->name ?? 'N/A',
                        $e->batch->name ?? 'N/A',
                        $e->mode ?? '',
                        $e->payable_fee ?? 0,
                        $e->paid_amount ?? 0,
                        $e->due_amount ?? 0,
                        $e->status ?? '',
                        $e->payment_status ?? '',
                        $e->created_at?->format('Y-m-d') ?? '',
                    ]);
                }
                fclose($handle);
            };
            return response()->stream($callback, 200, $headers);
        }

        // JSON fallback
        return $this->success($enrollments);
    }

    private function numberToWords($num): string {
        $ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine'];
        $tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
        $teens = ['Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
        if ($num < 10) return $ones[$num];
        if ($num < 20) return $teens[$num-10];
        if ($num < 100) return $tens[(int)($num/10)].($num%10 ? ' '.$ones[$num%10] : '');
        if ($num < 1000) return $ones[(int)($num/100)].' Hundred'.($num%100 ? ' '.$this->numberToWords($num%100) : '');
        if ($num < 1000000) return $this->numberToWords((int)($num/1000)).' Thousand'.($num%1000 ? ' '.$this->numberToWords($num%1000) : '');
        return (string)$num;
    }
}
