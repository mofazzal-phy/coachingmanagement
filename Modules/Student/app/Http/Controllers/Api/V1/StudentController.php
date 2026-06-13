<?php

namespace Modules\Student\app\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Modules\Student\app\Models\Student;
use Modules\Student\app\Models\Guardian;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Core\app\Services\FileUploadService;
use Modules\Enrollment\app\Models\Enrollment;
use Modules\Enrollment\app\Models\MonthlyFeeRecord;
use Modules\Enrollment\app\Services\PaymentService;
use Modules\Enrollment\app\Services\MonthlyFeeService;

class StudentController extends BaseApiController
{
    public function __construct(
        protected FileUploadService $fileUploadService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = $this->getPerPage($request);
        $query = Student::with(['guardian'])
            ->search($request->search)
            ->filter($request->only(['status', 'current_class_id', 'current_section_id', 'gender']));

        // Filter by enrollment status: 'enrolled', 'non_enrolled', or all (default)
        if ($enrollmentStatus = $request->enrollment_status) {
            if ($enrollmentStatus === 'enrolled') {
                $query->whereHas('enrollments');
            } elseif ($enrollmentStatus === 'non_enrolled') {
                $query->whereDoesntHave('enrollments');
            }
        }

        $students = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Append enrollment_status count to each student
        $students->getCollection()->transform(function ($student) {
            $student->loadCount('enrollments');
            return $student;
        });

        return $this->paginatedResponse($students);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|string|exists:users,id',
            'student_id' => 'nullable|string|unique:students,student_id',
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:students,email,NULL,id,deleted_at,NULL',
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'blood_group' => 'nullable|string|max:10',
            'religion' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'present_address' => 'nullable|string|max:500',
            'permanent_address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'current_class_id' => 'nullable|string|exists:classes,id',
            'current_section_id' => 'nullable|string|exists:sections,id',
            'group_id' => 'nullable|integer|exists:academic_groups,id',
            'roll_no' => 'nullable|string|max:20',
            'academic_session_id' => 'nullable|string|exists:academic_sessions,id',
            'admission_date' => 'nullable|date',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'father_name' => 'nullable|string|max:255',
            'father_phone' => 'nullable|string|max:20',
            'father_occupation' => 'nullable|string|max:255',
            'mother_name' => 'nullable|string|max:255',
            'mother_phone' => 'nullable|string|max:20',
            'mother_occupation' => 'nullable|string|max:255',
            'emergency_contact' => 'nullable|string|max:255',
            'emergency_phone' => 'nullable|string|max:20',
            'previous_school' => 'nullable|string|max:255',
            'previous_class' => 'nullable|string|max:100',
            'remarks' => 'nullable|string',
            // Login account fields
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|min:6|max:255',
        ]);

        // Auto-generate student_id if not provided (with race condition guard)
        if (empty($validated['student_id'])) {
            $year = now()->format('Y');
            $maxAttempts = 10;

            for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
                $lastStudent = Student::whereYear('created_at', $year)
                    ->orderBy('student_id', 'desc')->first();
                $sequence = $lastStudent ? (int) substr($lastStudent->student_id ?? '000000', -6) + 1 : 1;
                $candidateId = 'STU-' . $year . '-' . str_pad($sequence, 6, '0', STR_PAD_LEFT);

                // Verify uniqueness including soft-deleted records (withTrashed)
                // because the DB unique constraint applies to all rows including soft-deleted ones
                if (!Student::withTrashed()->where('student_id', $candidateId)->exists()) {
                    $validated['student_id'] = $candidateId;
                    break;
                }
            }

            // Fallback if all attempts exhausted
            if (empty($validated['student_id'])) {
                $validated['student_id'] = 'STU-' . $year . '-' . now()->format('His');
            }
        }

        // Auto-set academic session to current
        if (empty($validated['academic_session_id'])) {
            $session = \Modules\Academic\app\Models\AcademicSession::where('is_current', true)->first();
            $validated['academic_session_id'] = $session?->id;
        }

        // Auto-set admission date
        if (empty($validated['admission_date'])) {
            $validated['admission_date'] = now()->toDateString();
        }

        // Default last_name if not provided
        if (empty($validated['last_name'])) {
            $validated['last_name'] = '';
        }

        if ($request->hasFile('photo')) {
            $validated['photo'] = $this->fileUploadService->upload($request->file('photo'), 'students/photos');
        } else {
            unset($validated['photo']);
        }

        // Extract login account fields before creating student
        $username = $validated['username'] ?? null;
        $password = $validated['password'] ?? null;
        unset($validated['username'], $validated['password']);

        // Create the student record
        $student = Student::create($validated);

        // Create user account if password is provided
        if ($password) {
            $this->createUserForStudent($student, $username, $password);
        }

        return $this->created($student->load('guardian'));
    }

    /**
     * Create a user account for the student and link it.
     */
    private function createUserForStudent(Student $student, ?string $username, string $password): void
    {
        $fullName = trim($student->first_name . ' ' . $student->last_name);
        $email = $student->email ?: $student->student_id . '@student.local';
        $phone = $student->phone;

        // Use provided username or fallback to student_id
        $name = $username ?: $student->student_id;

        // Check if a user with this email already exists
        $existingUser = User::where('email', $email)->first();
        if ($existingUser) {
            // Link existing user to student
            $student->update(['user_id' => $existingUser->id]);
            return;
        }

        // Create the user
        $user = User::create([
            'name'     => $name,
            'email'    => $email,
            'phone'    => $phone,
            'password' => Hash::make($password),
        ]);

        // Assign student role
        $user->assignRole('student');

        // Link student record to the newly created user
        $student->update(['user_id' => $user->id]);
    }

    public function show(string $id): JsonResponse
    {
        $student = Student::with([
            'guardian',
            'enrollments.batch.course',
            'enrollments.batch.academicSession',
            'enrollments.subjects',
            'attendances',
            'feeCollections.feeType',
            'examResults.subject'
        ])->withCount('enrollments')->find($id);

        if (!$student) return $this->notFound();
        return $this->success($student);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $student = Student::find($id);
        if (!$student) return $this->notFound();

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'nullable|email|unique:students,email,' . $id . ',id,deleted_at,NULL',
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'blood_group' => 'nullable|string|max:10',
            'religion' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'current_class_id' => 'sometimes|string|exists:classes,id',
            'current_section_id' => 'nullable|string|exists:sections,id',
            'roll_no' => 'nullable|string|max:20',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'father_name' => 'nullable|string|max:255',
            'father_phone' => 'nullable|string|max:20',
            'father_occupation' => 'nullable|string|max:255',
            'mother_name' => 'nullable|string|max:255',
            'mother_phone' => 'nullable|string|max:20',
            'mother_occupation' => 'nullable|string|max:255',
            'emergency_contact' => 'nullable|string|max:255',
            'emergency_phone' => 'nullable|string|max:20',
            'previous_school' => 'nullable|string|max:255',
            'previous_class' => 'nullable|string|max:100',
            'status' => 'sometimes|in:active,inactive,graduated,transferred,expelled',
            'remarks' => 'nullable|string',
        ]);

        if ($request->hasFile('photo')) {
            $validated['photo'] = $this->fileUploadService->replace(
                $student->photo,
                $request->file('photo'),
                'students/photos'
            );
        } else {
            unset($validated['photo']);
        }

        $student->update($validated);
        return $this->success($student->fresh(['guardian']));
    }

    public function destroy(string $id): JsonResponse
    {
        $student = Student::find($id);
        if (!$student) return $this->notFound();
        $student->delete();
        return $this->noContent();
    }

    // === Guardian Management ===
    public function storeGuardian(Request $request, string $studentId): JsonResponse
    {
        $student = Student::find($studentId);
        if (!$student) return $this->notFound('Student not found');

        $validated = $request->validate([
            'father_name' => 'nullable|string|max:255',
            'father_phone' => 'nullable|string|max:20',
            'father_email' => 'nullable|email',
            'father_occupation' => 'nullable|string|max:255',
            'mother_name' => 'nullable|string|max:255',
            'mother_phone' => 'nullable|string|max:20',
            'mother_email' => 'nullable|email',
            'mother_occupation' => 'nullable|string|max:255',
            'guardian_name' => 'nullable|string|max:255',
            'guardian_relation' => 'nullable|string|max:100',
            'guardian_phone' => 'nullable|string|max:20',
            'guardian_email' => 'nullable|email',
            'address' => 'nullable|string',
        ]);

        $validated['student_id'] = $studentId;
        $guardian = Guardian::updateOrCreate(['student_id' => $studentId], $validated);

        return $this->success($guardian);
    }

    public function getGuardian(string $studentId): JsonResponse
    {
        $guardian = Guardian::where('student_id', $studentId)->first();
        if (!$guardian) return $this->notFound('Guardian not found');
        return $this->success($guardian);
    }

    /**
     * Get comprehensive fee payment summary for a student across all enrollments.
     * Returns total fee, paid, due, monthly fee breakdown, and payment history.
     */
    public function feeSummary(string $id): JsonResponse
    {
        $student = Student::with([
            'enrollments.batch.course',
            'enrollments.payments',
            'enrollments.monthlyFeeRecords',
        ])->find($id);

        if (!$student) return $this->notFound('Student not found');

        $enrollments = $student->enrollments;
        $summary = [];

        foreach ($enrollments as $enrollment) {
            $monthlyFeeService = app(MonthlyFeeService::class);
            $monthlySummary = null;
            $lastPaidMonth = null;
            $nextPendingMonth = null;

            if ($enrollment->fee_type === 'monthly') {
                try {
                    $monthlySummary = $monthlyFeeService->getSummary($enrollment->id);

                    // Extract last paid month info from monthly summary
                    if ($monthlySummary && isset($monthlySummary['last_paid_month'])) {
                        $lastPaidMonth = [
                            'month' => $monthlySummary['last_paid_month']['month'] ?? null,
                            'month_name' => $monthlySummary['last_paid_month_name'] ?? null,
                            'paid_amount' => $monthlySummary['last_paid_amount'] ?? 0,
                        ];
                    }

                    // Extract next pending month info
                    if ($monthlySummary && isset($monthlySummary['next_unpaid_month'])) {
                        $nextPendingMonth = [
                            'month' => $monthlySummary['next_unpaid_month']['month'] ?? null,
                            'month_name' => $monthlySummary['next_month_name'] ?? null,
                            'due_amount' => $monthlySummary['next_month_due'] ?? 0,
                            'status' => $monthlySummary['next_month_status'] ?? null,
                            'record_id' => $monthlySummary['next_unpaid_month']['id'] ?? null,
                        ];
                    }
                } catch (\Exception $e) {
                    $monthlySummary = null;
                }
            }

            $summary[] = [
                'enrollment_id' => $enrollment->id,
                'enrollment_no' => $enrollment->enrollment_no,
                'course_name' => $enrollment->batch?->course?->name ?? 'N/A',
                'batch_name' => $enrollment->batch?->name ?? 'N/A',
                'fee_type' => $enrollment->fee_type,
                'total_fee' => (float) ($enrollment->total_fee ?? 0),
                'payable_fee' => (float) ($enrollment->payable_fee ?? 0),
                'paid_amount' => (float) ($enrollment->paid_amount ?? 0),
                'due_amount' => (float) ($enrollment->due_amount ?? 0),
                'discount_percent' => (float) ($enrollment->discount_percent ?? 0),
                'payment_status' => $enrollment->payment_status,
                'enrollment_status' => $enrollment->status,
                'monthly_summary' => $monthlySummary,
                'last_paid_month' => $lastPaidMonth,
                'next_pending_month' => $nextPendingMonth,
                'recent_payments' => $enrollment->payments()
                    ->orderBy('created_at', 'desc')
                    ->take(5)
                    ->get()
                    ->toArray(),
            ];
        }

        // Overall totals
        $totalFee = $enrollments->sum('total_fee');
        $totalPayable = $enrollments->sum('payable_fee');
        $totalPaid = $enrollments->sum('paid_amount');
        $totalDue = $enrollments->sum('due_amount');

        // Calculate total discount across all monthly enrollments
        $totalDiscount = 0;
        foreach ($enrollments as $enrollment) {
            if ($enrollment->fee_type === 'monthly') {
                $monthlyFeeService = app(MonthlyFeeService::class);
                try {
                    $monthlySummary = $monthlyFeeService->getSummary($enrollment->id);
                    $totalDiscount += $monthlySummary['total_discount'] ?? 0;
                } catch (\Exception $e) {
                    // Skip if summary fails
                }
            } elseif ($enrollment->discount_percent > 0) {
                $totalDiscount += $enrollment->total_fee * $enrollment->discount_percent / 100;
            }
        }

        return $this->success([
            'student' => [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'phone' => $student->phone,
                'email' => $student->email,
            ],
            'overall' => [
                'total_enrollments' => $enrollments->count(),
                'total_fee' => $totalFee,
                'total_payable' => $totalPayable,
                'total_paid' => $totalPaid,
                'total_due' => $totalDue,
                'total_discount' => $totalDiscount,
                'payment_percentage' => $totalPayable > 0 ? round(($totalPaid / $totalPayable) * 100, 2) : 0,
            ],
            'enrollments' => $summary,
        ]);
    }

    /**
     * Record a payment for a student's enrollment directly from the student details page.
     * For monthly fee type, accepts an optional monthly_record_id to pay for a specific month.
     * If monthly_record_id is not provided, pays against the next unpaid month.
     */
    public function recordPayment(Request $request, string $id): JsonResponse
    {
        $student = Student::find($id);
        if (!$student) return $this->notFound('Student not found');

        $validated = $request->validate([
            'enrollment_id' => 'required|exists:enrollments,id',
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'nullable|string|max:50',
            'reference' => 'nullable|string|max:100',
            'transaction_id' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:500',
            'monthly_record_id' => 'nullable|exists:monthly_fee_records,id',
        ]);

        // Verify the enrollment belongs to this student
        $enrollment = Enrollment::where('id', $validated['enrollment_id'])
            ->where('student_id', $student->id)
            ->first();

        if (!$enrollment) {
            return $this->error('Enrollment not found for this student.', 404);
        }

        try {
            // For monthly fee type, record against a specific monthly record
            if ($enrollment->fee_type === 'monthly') {
                $monthlyFeeService = app(MonthlyFeeService::class);

                // Determine which monthly record to pay for
                $recordId = $validated['monthly_record_id'] ?? null;

                if ($recordId) {
                    // Use the specified monthly record
                    $monthlyRecord = MonthlyFeeRecord::where('id', $recordId)
                        ->where('enrollment_id', $enrollment->id)
                        ->first();

                    if (!$monthlyRecord) {
                        return $this->error('Monthly fee record not found for this enrollment.', 404);
                    }

                    if ($monthlyRecord->payment_status === 'paid') {
                        return $this->error('This month has already been fully paid.', 422);
                    }
                } else {
                    // Find the next unpaid monthly record
                    $monthlyRecord = MonthlyFeeRecord::where('enrollment_id', $enrollment->id)
                        ->where('payment_status', '!=', 'paid')
                        ->orderBy('month', 'asc')
                        ->first();

                    if (!$monthlyRecord) {
                        return $this->error('No pending monthly fee records found for this enrollment.', 422);
                    }
                }

                $record = $monthlyFeeService->recordPayment(
                    $monthlyRecord->id,
                    $validated['amount'],
                    $validated['payment_method'] ?? 'cash',
                    $validated['transaction_id'] ?? null,
                    $validated['reference'] ?? null,
                    $validated['note'] ?? null,
                    null,
                    null,
                    null,
                    $request->user()?->id
                );

                return $this->success([
                    'enrollment_id' => $enrollment->id,
                    'monthly_record_id' => $record->id,
                    'month' => $record->month,
                    'month_name' => \Carbon\Carbon::parse($record->month . '-01')->format('F Y'),
                    'amount' => $validated['amount'],
                    'payment_method' => $validated['payment_method'] ?? 'cash',
                    'payment_status' => $record->payment_status,
                    'message' => 'Monthly fee payment recorded successfully for ' . \Carbon\Carbon::parse($record->month . '-01')->format('F Y') . '.',
                ], 'Payment recorded successfully');
            }

            // For one-time fee type, use the existing payment service
            $paymentService = app(PaymentService::class);
            $payment = $paymentService->recordPayment(
                $enrollment,
                $validated['amount'],
                $validated['payment_method'] ?? 'cash',
                $validated['reference'] ?? null,
                $validated['transaction_id'] ?? null
            );

            $enrollmentService = app(\Modules\Enrollment\app\Services\EnrollmentService::class);
            $enrollment = $enrollmentService->recordPayment(
                $enrollment,
                $validated['amount'],
                $validated['payment_method'] ?? 'cash',
                $validated['reference'] ?? null,
                $validated['transaction_id'] ?? null
            );

            return $this->success([
                'enrollment_id' => $enrollment->id,
                'payment_id' => $payment->id,
                'receipt_no' => $payment->receipt_no,
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'] ?? 'cash',
                'payment_status' => 'paid',
                'message' => 'Payment recorded successfully.',
            ], 'Payment recorded successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
}
