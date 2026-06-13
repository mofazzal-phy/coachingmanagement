<?php

namespace Modules\Enrollment\app\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Enrollment\app\Models\Course;
use Modules\Enrollment\app\Models\Batch;
use Modules\Enrollment\app\Models\Enrollment;
use Modules\Enrollment\app\Services\EnrollmentService;
use Modules\Student\app\Models\Student;
use Modules\Student\app\Models\Guardian;

class PublicController extends BaseApiController
{
    protected $enrollmentService;

    public function __construct(EnrollmentService $enrollmentService)
    {
        $this->enrollmentService = $enrollmentService;
    }

    public function courses(Request $request)
    {
        $query = Course::with(['subjects', 'class', 'group'])
            ->active()
            ->where(function ($q) {
                $q->where('has_online', true)->orWhere('has_offline', true);
            });

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        if ($request->filled('group_id')) {
            $query->where('group_id', $request->group_id);
        }

        $courses = $query->orderBy('sort_order')->get();

        return $this->success($courses);
    }

    public function courseDetails($id)
    {
        $course = Course::with([
            'subjects',
            'class',
            'group',
            'batches' => function ($q) {
                $q->whereIn('status', ['open', 'upcoming'])
                    ->with(['room', 'teacher']);
            },
        ])->findOrFail($id);

        $course->batches->map(function ($batch) {
            $batch->available_seats = $batch->availableSeats();
            return $batch;
        });

        return $this->success($course);
    }

    public function batches($courseId)
    {
        $course = Course::findOrFail($courseId);
        $batches = $this->enrollmentService->getAvailableBatches($course, request('mode'));

        return $this->success($batches);
    }

    public function calculateFee(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'subject_ids' => 'nullable|array',
            'subject_ids.*' => 'exists:subjects,id',
            'fee_type' => 'nullable|in:one_time,monthly',
        ]);

        $course = Course::findOrFail($validated['course_id']);
        $feeCalculation = $this->enrollmentService->calculateFee(
            $course,
            $validated['subject_ids'] ?? [],
            null,
            $validated['fee_type'] ?? 'one_time'
        );

        return $this->success($feeCalculation);
    }

    /**
     * Public self-enrollment / admission — no login, no pre-registration.
     *
     * Payment:
     *  - cash            => enrollment stays "pending" until an admin approves it;
     *                       login account is created on approval.
     *  - online gateway  => payment is captured (sandbox/mock in dev), enrollment is
     *                       auto-activated and a login account + credentials are issued instantly.
     *
     * A full-detail PDF is always available right after submission.
     */
    public function applyOnline(Request $request)
    {
        // Honeypot: bots fill hidden fields that humans never see.
        if (filled($request->input('website')) || filled($request->input('company'))) {
            return $this->error('Submission rejected.', 422);
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:120', 'regex:/^[\pL\pM .\'-]+$/u'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'email' => 'nullable|email:rfc|max:255',
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s]{6,20}$/'],
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|string|in:male,female,other',
            'present_address' => 'nullable|string|max:500',
            'previous_school' => 'nullable|string|max:255',
            'guardian_name' => ['required', 'string', 'max:160'],
            'guardian_phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s]{6,20}$/'],
            'guardian_email' => 'nullable|email:rfc|max:255',
            'relationship' => 'nullable|string|max:100',
            'class_id' => 'required|uuid|exists:classes,id',
            'group_id' => 'nullable|exists:academic_groups,id',
            'course_id' => 'nullable|uuid|exists:courses,id',
            'batch_id' => 'required|uuid|exists:batches,id',
            'subject_ids' => 'nullable|array|max:40',
            'subject_ids.*' => 'uuid|exists:subjects,id',
            'fee_type' => 'nullable|in:one_time,monthly',
            'payment_method' => 'nullable|string|in:cash,bkash,nagad,rocket,card,bank',
            'pay_online' => 'nullable|boolean',
            'username' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/'],
            'password' => 'nullable|string|min:6|max:64',
        ]);

        $payOnline = (bool) ($validated['pay_online'] ?? false)
            && ($validated['payment_method'] ?? 'cash') !== 'cash';
        $feeType = $validated['fee_type'] ?? 'one_time';
        $subjectIds = $validated['subject_ids'] ?? [];

        try {
            $batch = Batch::with('course')->findOrFail($validated['batch_id']);
            $course = $batch->course;

            // Auto-include the course's mandatory subjects when none were chosen,
            // so fees + attached subjects match the admin enrollment flow exactly.
            if (empty($subjectIds)) {
                $subjectIds = $course->subjects()->wherePivot('is_mandatory', true)->pluck('subjects.id')->toArray();
                if (empty($subjectIds)) {
                    $subjectIds = $course->subjects->pluck('id')->toArray();
                }
            }

            // ---- Student (create new, or reuse the record with the same phone) ----
            // On reuse we refresh the profile fields from this submission so the
            // latest name/details the applicant typed are reflected everywhere
            // (PDF, admin list, etc.) instead of keeping a stale name.
            $student = Student::where('phone', $validated['phone'])->first();
            $studentData = [
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'] ?: '-',
                'email' => $validated['email'] ?? ($student->email ?? null),
                'date_of_birth' => $validated['date_of_birth'] ?? ($student->date_of_birth ?? null),
                'gender' => $validated['gender'] ?? ($student->gender ?? 'male'),
                'present_address' => $validated['present_address'] ?? ($student->present_address ?? null),
                'previous_school' => $validated['previous_school'] ?? ($student->previous_school ?? null),
                'current_class_id' => $validated['class_id'],
                'group_id' => $validated['group_id'] ?? ($student->group_id ?? null),
            ];

            if (!$student) {
                $student = Student::create(array_merge($studentData, [
                    'student_id' => $this->enrollmentService->generateStudentId(),
                    'phone' => $validated['phone'],
                    'admission_date' => now()->toDateString(),
                    'status' => 'active',
                ]));
            } else {
                $student->update($studentData);
            }

            // ---- Guardian (schema-accurate columns) ----
            Guardian::updateOrCreate(
                ['student_id' => $student->id],
                [
                    'name' => $validated['guardian_name'],
                    'relation' => $this->mapRelation($validated['relationship'] ?? null),
                    'phone' => $validated['guardian_phone'],
                    'email' => $validated['guardian_email'] ?? null,
                    'is_primary' => true,
                ]
            );

            // ---- Fee + amount due now (mirrors admin calculation) ----
            $feeCalc = $this->enrollmentService->calculateFee($course, $subjectIds, $student, $feeType);
            $enrollmentFee = (float) ($feeCalc['enrollment_fee'] ?? $course->enrollment_fee ?? 0);
            $payableFee = (float) ($feeCalc['payable_fee'] ?? 0);
            $amountNow = $enrollmentFee > 0 ? $enrollmentFee : $payableFee;

            // The admission (enrollment) fee is collected the moment the form is
            // submitted — so it shows as PAID on the receipt/PDF and in the admin
            // portal even before approval. Online additionally settles the amount
            // due now; cash applicants pay the remaining course fee at the centre.
            $paidNow = $payOnline ? $amountNow : $enrollmentFee;

            $enrollData = [
                'course_id' => $course->id,
                'batch_id' => $batch->id,
                'subject_ids' => $subjectIds,
                'enrolled_class_id' => $validated['class_id'],
                'enrolled_group_id' => $validated['group_id'] ?? null,
                'fee_type' => $feeType,
                'guardian_phone' => $validated['guardian_phone'],
                'guardian_email' => $validated['guardian_email'] ?? null,
                'source' => 'public',
                'payment_method' => $payOnline ? ($validated['payment_method'] ?? 'bkash') : 'cash',
                'paid_amount' => $paidNow,
            ];

            if ($payOnline) {
                // Sandbox/mock capture — replace with real gateway callback when keys are configured.
                $enrollData['payment_transaction_id'] = 'TXN-' . strtoupper(Str::random(12));
                $enrollData['payment_reference'] = 'online';
            }

            $enrollment = $this->enrollmentService->enroll($student, $enrollData);

            // Public submissions always wait for admin approval before going active,
            // even though the admission fee is already recorded as paid.
            if (!$payOnline && $enrollment->status === 'active') {
                $enrollment->update(['status' => 'pending']);
            }

            // ---- Login account ----
            // Online  => active immediately (student can log in now).
            // Cash    => created but inactive; activated when the admin approves.
            $login = $this->enrollmentService->provisionStudentLogin(
                $student,
                $validated['username'] ?? null,
                $validated['password'] ?? null,
                $payOnline ? 'active' : 'inactive'
            );

            // Surface the login username (and password when we created/changed it).
            $credentials = null;
            if (!empty($login['username'])) {
                $credentials = [
                    'username' => $login['username'],
                    'password' => $login['password'] ?? null,
                ];
            }

            $enrollment->refresh()->load(['student', 'batch.course', 'subjects']);

            $enrollmentFeePaid = (float) $enrollment->enrollment_fee_paid;
            $courseFeePaid = (float) $enrollment->paid_amount;

            return $this->created([
                'enrollment_no' => $enrollment->enrollment_no,
                'status' => $enrollment->status,
                'payment' => $payOnline ? 'paid' : 'cash_pending',
                'pay_online' => $payOnline,
                'login_active' => $payOnline,
                'amount_paid' => $enrollmentFeePaid + $courseFeePaid,
                'fees' => [
                    'enrollment_fee' => (float) $enrollment->enrollment_fee,
                    'enrollment_fee_paid' => $enrollmentFeePaid,
                    'course_payable' => (float) $enrollment->payable_fee,
                    'total_paid' => $enrollmentFeePaid + $courseFeePaid,
                    'due' => (float) $enrollment->due_amount,
                ],
                'credentials' => $credentials,
                'pdf_url' => url('/api/v1/enrollment/public/' . $enrollment->enrollment_no . '/pdf'),
                'enrollment' => $enrollment,
            ], $payOnline
                ? 'Enrollment confirmed! Use your credentials to log in.'
                : 'Application submitted! Save your credentials — you can log in once the admin approves your enrollment.');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function trackStatus($enrollmentNo)
    {
        $enrollment = Enrollment::with([
            'student:id,first_name,last_name,phone,student_id,user_id',
            'batch:id,name,mode,start_time,end_time,days',
            'batch.course:id,name,category',
            'subjects:id,name',
        ])->where('enrollment_no', $enrollmentNo)->first();

        if (!$enrollment) {
            return $this->notFound('Enrollment not found');
        }

        $data = $enrollment->toArray();
        // Surface whether a login is ready (without leaking the password).
        $data['login_ready'] = (bool) ($enrollment->student?->user_id) && $enrollment->status === 'active';
        $data['login_username'] = $data['login_ready'] ? $enrollment->student?->student_id : null;

        return $this->success($data);
    }

    /**
     * Stream a full-detail enrollment PDF (generated on the fly).
     */
    public function enrollmentPdf($enrollmentNo)
    {
        $enrollment = Enrollment::with([
            'student.guardian',
            'batch.course',
            'subjects',
        ])->where('enrollment_no', $enrollmentNo)->firstOrFail();

        $html = $this->buildEnrollmentHtml($enrollment);

        $pdf = Pdf::loadHTML($html)->setPaper('A4', 'portrait');

        return $pdf->stream('enrollment-' . $enrollment->enrollment_no . '.pdf');
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function mapRelation(?string $relationship): string
    {
        $r = strtolower(trim((string) $relationship));
        $allowed = ['father', 'mother', 'brother', 'sister', 'uncle', 'aunt', 'grandfather', 'grandmother', 'other'];

        return in_array($r, $allowed, true) ? $r : 'father';
    }

    private function buildEnrollmentHtml(Enrollment $enrollment): string
    {
        $student = $enrollment->student;
        $guardian = $student?->guardian;
        $course = $enrollment->batch?->course;
        $batch = $enrollment->batch;

        $instituteName = config('app.name', 'Poralekha');
        $generatedAt = now()->format('d M, Y h:i A');

        $statusLabels = [
            'active' => ['Confirmed', '#065f46', '#d1fae5'],
            'pending' => ['Pending Approval', '#92400e', '#fef3c7'],
            'waiting' => ['Waiting List', '#1e40af', '#dbeafe'],
            'completed' => ['Completed', '#3730a3', '#e0e7ff'],
            'dropped' => ['Dropped', '#991b1b', '#fee2e2'],
        ];
        [$statusText, $statusColor, $statusBg] = $statusLabels[$enrollment->status] ?? [ucfirst($enrollment->status), '#374151', '#e5e7eb'];

        $studentName = trim(($student->first_name ?? '') . ' ' . ($student->last_name === '-' ? '' : ($student->last_name ?? '')));
        $studentId = $student->student_id ?? 'N/A';
        $phone = $student->phone ?? 'N/A';
        $email = $student->email ?? 'N/A';
        $dob = $student->date_of_birth ? \Carbon\Carbon::parse($student->date_of_birth)->format('d M, Y') : 'N/A';
        $gender = ucfirst($student->gender ?? 'N/A');

        $guardianName = $guardian->name ?? 'N/A';
        $guardianRelation = ucfirst($guardian->relation ?? '');
        $guardianPhone = $guardian->phone ?? ($enrollment->guardian_phone ?? 'N/A');

        $courseName = $course->name ?? 'N/A';
        $batchName = $batch->name ?? 'N/A';
        $mode = ucfirst($enrollment->mode ?? ($batch->mode ?? 'N/A'));

        $subjectsRows = '';
        foreach ($enrollment->subjects as $i => $sub) {
            $n = $i + 1;
            $subjectsRows .= "<tr><td>{$n}</td><td>{$sub->name}</td></tr>";
        }
        if ($subjectsRows === '') {
            $subjectsRows = '<tr><td colspan="2" style="text-align:center;color:#888;">All course subjects included</td></tr>';
        }

        $isMonthly = $enrollment->fee_type === 'monthly';
        $feeType = $isMonthly ? 'Monthly' : 'One-time';

        // Raw values
        $totalFeeVal = (float) $enrollment->total_fee;
        $payableFeeVal = (float) $enrollment->payable_fee;
        $enrollmentFeeVal = (float) $enrollment->enrollment_fee;
        $enrollmentFeePaidVal = (float) $enrollment->enrollment_fee_paid;
        $courseFeePaidVal = (float) $enrollment->paid_amount;
        $discountVal = max(0, $totalFeeVal - $payableFeeVal);
        $grandTotalVal = $enrollmentFeeVal + $payableFeeVal;
        $totalPaidVal = $enrollmentFeePaidVal + $courseFeePaidVal;
        $dueVal = (float) $enrollment->due_amount;

        // Formatted
        $totalFee = number_format($totalFeeVal, 2);
        $payableFee = number_format($payableFeeVal, 2);
        $enrollmentFee = number_format($enrollmentFeeVal, 2);
        $enrollmentFeePaid = number_format($enrollmentFeePaidVal, 2);
        $courseFeePaidStr = number_format($courseFeePaidVal, 2);
        $discount = number_format($discountVal, 2);
        $grandTotal = number_format($grandTotalVal, 2);
        $paid = number_format($totalPaidVal, 2);
        $due = number_format($dueVal, 2);
        $courseFeeLabel = $isMonthly ? 'Course Fee (Total, all months)' : 'Course Fee';
        $payableLabel = $isMonthly ? 'Course Payable (after discount)' : 'Course Payable Fee';
        $paymentMethod = ucfirst($enrollment->payment_method ?? 'N/A');
        $paymentStatus = ucfirst($enrollment->payment_status ?? 'pending');
        $txn = $enrollment->payment_transaction_id ?? 'N/A';
        $discountRow = $discountVal > 0
            ? "<tr><td class=\"label\">Discount</td><td style=\"text-align:right;color:#047857;\">- BDT {$discount}</td></tr>"
            : '';

        $loginReady = $student?->user_id && $enrollment->status === 'active';
        $loginBlock = '';
        if ($loginReady) {
            $loginBlock = <<<LOGIN
        <div class="card" style="background:#eef2ff;border-color:#c7d2fe;">
            <h4>Student Portal Login</h4>
            <table>
                <tr><td class="label">Username</td><td><strong>{$studentId}</strong></td></tr>
                <tr><td class="label">Password</td><td>Provided to you at enrollment / by SMS &amp; email</td></tr>
                <tr><td class="label">Login URL</td><td>/login</td></tr>
            </table>
        </div>
LOGIN;
        } elseif ($enrollment->status === 'pending') {
            $loginBlock = '<div class="note">Your login will be activated once the admin confirms your payment. Track status anytime with your enrollment number.</div>';
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1f2937; margin: 0; }
    .box { max-width: 720px; margin: 0 auto; padding: 28px; }
    .header { text-align: center; border-bottom: 3px solid #4f46e5; padding-bottom: 16px; margin-bottom: 18px; }
    .header h1 { color: #4f46e5; font-size: 26px; margin: 0; }
    .header p { color: #6b7280; font-size: 11px; margin: 4px 0 0; }
    .title-row { display: flex; justify-content: space-between; align-items: center; margin: 16px 0; }
    h3.doc-title { font-size: 15px; color: #4f46e5; border: 1px solid #4f46e5; display: inline-block; padding: 7px 22px; border-radius: 6px; margin: 0; }
    .badge { display: inline-block; padding: 5px 14px; border-radius: 14px; font-size: 11px; font-weight: bold; color: {$statusColor}; background: {$statusBg}; }
    .card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px 16px; margin: 12px 0; }
    .card h4 { color: #4f46e5; margin: 0 0 8px; font-size: 13px; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 4px 6px; font-size: 12px; vertical-align: top; }
    .label { font-weight: bold; color: #6b7280; width: 140px; }
    .details-table th { background: #4f46e5; color: #fff; padding: 8px 10px; text-align: left; font-size: 11px; }
    .details-table td { border-bottom: 1px solid #eef2f7; padding: 7px 10px; }
    .fee td { padding: 5px 6px; }
    .fee .total { font-weight: bold; border-top: 2px solid #4f46e5; font-size: 13px; }
    .note { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; border-radius: 8px; padding: 12px 14px; margin: 12px 0; font-size: 11px; }
    .footer { text-align: center; margin-top: 24px; padding-top: 12px; border-top: 1px solid #e5e7eb; font-size: 10px; color: #9ca3af; }
</style>
</head>
<body>
<div class="box">
    <div class="header">
        <h1>{$instituteName}</h1>
        <p>Enrollment / Admission Confirmation</p>
    </div>

    <div class="title-row">
        <h3 class="doc-title">ENROLLMENT FORM</h3>
        <span class="badge">{$statusText}</span>
    </div>

    <div class="card">
        <table>
            <tr>
                <td class="label">Enrollment No</td><td><strong>{$enrollment->enrollment_no}</strong></td>
                <td class="label">Date</td><td>{$generatedAt}</td>
            </tr>
        </table>
    </div>

    <div class="card">
        <h4>Student Information</h4>
        <table>
            <tr><td class="label">Name</td><td>{$studentName}</td><td class="label">Student ID</td><td>{$studentId}</td></tr>
            <tr><td class="label">Phone</td><td>{$phone}</td><td class="label">Email</td><td>{$email}</td></tr>
            <tr><td class="label">Date of Birth</td><td>{$dob}</td><td class="label">Gender</td><td>{$gender}</td></tr>
        </table>
    </div>

    <div class="card">
        <h4>Guardian Information</h4>
        <table>
            <tr><td class="label">Name</td><td>{$guardianName}</td><td class="label">Relation</td><td>{$guardianRelation}</td></tr>
            <tr><td class="label">Phone</td><td>{$guardianPhone}</td></tr>
        </table>
    </div>

    <div class="card">
        <h4>Course &amp; Batch</h4>
        <table>
            <tr><td class="label">Course</td><td>{$courseName}</td><td class="label">Mode</td><td>{$mode}</td></tr>
            <tr><td class="label">Batch</td><td>{$batchName}</td><td class="label">Fee Type</td><td>{$feeType}</td></tr>
        </table>
        <table class="details-table" style="margin-top:10px;">
            <thead><tr><th style="width:48px;">#</th><th>Subject</th></tr></thead>
            <tbody>{$subjectsRows}</tbody>
        </table>
    </div>

    <div class="card">
        <h4>Fee &amp; Payment</h4>
        <table class="fee">
            <tr><td class="label">{$courseFeeLabel}</td><td style="text-align:right;">BDT {$totalFee}</td></tr>
            {$discountRow}
            <tr><td class="label">{$payableLabel}</td><td style="text-align:right;">BDT {$payableFee}</td></tr>
            <tr><td class="label">Admission / Enrollment Fee</td><td style="text-align:right;">BDT {$enrollmentFee}</td></tr>
            <tr class="total"><td>Grand Total (Admission + Course)</td><td style="text-align:right;">BDT {$grandTotal}</td></tr>
            <tr><td class="label">Enrollment Fee Paid</td><td style="text-align:right;color:#047857;">BDT {$enrollmentFeePaid}</td></tr>
            <tr><td class="label">Course Fee Paid</td><td style="text-align:right;color:#047857;">BDT {$courseFeePaidStr}</td></tr>
            <tr class="total"><td>Total Paid</td><td style="text-align:right;color:#047857;">BDT {$paid}</td></tr>
            <tr><td class="label">Due (payable at centre)</td><td style="text-align:right;">BDT {$due}</td></tr>
            <tr><td class="label">Method</td><td style="text-align:right;">{$paymentMethod}</td></tr>
            <tr><td class="label">Payment Status</td><td style="text-align:right;">{$paymentStatus}</td></tr>
            <tr><td class="label">Transaction ID</td><td style="text-align:right;">{$txn}</td></tr>
        </table>
    </div>

    {$loginBlock}

    <div class="footer">
        <p>This is a computer-generated document. Keep your enrollment number safe to track your status.</p>
        <p>{$instituteName} &middot; Thank you for choosing us!</p>
    </div>
</div>
</body>
</html>
HTML;
    }
}
