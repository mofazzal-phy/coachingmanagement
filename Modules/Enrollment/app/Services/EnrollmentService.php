<?php

namespace Modules\Enrollment\app\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Modules\Enrollment\app\Models\Course;
use Modules\Enrollment\app\Models\Batch;
use Modules\Enrollment\app\Models\Enrollment;
use Modules\Student\app\Models\Student;
use Modules\Student\app\Models\Guardian;
use Modules\Academic\app\Models\AcademicSession;
use Modules\Academic\app\Models\Classes;
use Modules\Academic\app\Models\AcademicGroup;

class EnrollmentService
{
    /**
     * Get courses by category with filters
     */
    public function getCoursesByCategory(string $category, array $filters = [])
    {
        $query = Course::with(['subjects', 'class', 'group'])
            ->where('category', $category)
            ->active();

        if (!empty($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        if (!empty($filters['group_id'])) {
            $query->where('group_id', $filters['group_id']);
        }

        if (!empty($filters['target'])) {
            $query->where('target', $filters['target']);
        }

        return $query->orderBy('sort_order')->get();
    }

    /**
     * Get courses for a specific class (academic)
     */
    public function getCoursesForClass($classId, $groupId = null)
    {
        $query = Course::with(['subjects', 'batches' => function ($q) {
            $q->where('status', 'open');
        }])->where('category', 'academic')
            ->where('class_id', $classId)
            ->active();

        if ($groupId) {
            $query->where('group_id', $groupId);
        }

        return $query->orderBy('sort_order')->get();
    }

    /**
     * Get available batches for a course
     */
    public function getAvailableBatches(Course $course, string $mode = null)
    {
        $query = $course->batches()->whereIn('status', ['open', 'upcoming']);

        if ($mode) {
            $query->where('mode', $mode);
        }

        return $query->with(['room', 'teacher'])->get()->map(function ($batch) {
            $batch->available_seats = $batch->availableSeats();
            return $batch;
        });
    }

    /**
     * Calculate fee with discounts
     */
    public function calculateFee(Course $course, array $subjectIds = [], Student $student = null, string $feeType = 'one_time', ?string $discountId = null, ?float $manualDiscountPercent = null): array
    {
        $totalFee = 0;
        $subjects = [];

        if (empty($subjectIds)) {
            // Full course: sum all mandatory subjects
            $courseSubjects = $course->subjects()->wherePivot('is_mandatory', true)->get();
            if ($courseSubjects->isEmpty()) {
                $courseSubjects = $course->subjects;
            }
            foreach ($courseSubjects as $subject) {
                $fee = $feeType === 'monthly' ? ($subject->pivot->monthly_fee ?? 0) : ($subject->pivot->fee ?? 0);
                $totalFee += $fee;
                $subjects[] = [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'fee' => $fee,
                    'monthly_fee' => $subject->pivot->monthly_fee ?? 0,
                    'is_mandatory' => (bool) ($subject->pivot->is_mandatory ?? true),
                ];
            }
        } else {
            // Selected subjects only
            $courseSubjects = $course->subjects()->whereIn('subjects.id', $subjectIds)->get();
            foreach ($courseSubjects as $subject) {
                $fee = $feeType === 'monthly' ? ($subject->pivot->monthly_fee ?? 0) : $subject->pivot->fee;
                $totalFee += $fee;
                $subjects[] = [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'fee' => $fee,
                    'monthly_fee' => $subject->pivot->monthly_fee ?? 0,
                    'is_mandatory' => (bool) $subject->pivot->is_mandatory,
                ];
            }
        }

        // Course-level one-time fee fallback when subject pivot fees are empty
        if ($feeType === 'one_time' && $totalFee <= 0 && ($course->one_time_fee ?? 0) > 0) {
            $totalFee = (float) $course->one_time_fee;
        }

        $enrollmentFee = (float) ($course->enrollment_fee ?? 0);

        // ====================================================================
        // DISCOUNT RULES:
        // Discount applies to BOTH fee types:
        //
        // For 'one_time' (admission/enrollment fee):
        //   - percentage: discount_percent% off total fee
        //   - fixed: flat amount off total fee (converted to equivalent %)
        //
        // For 'monthly' (recurring monthly fee):
        //   - percentage: discount_percent% off each month's fee
        //   - fixed: flat amount off each month's fee (preferable for monthly)
        //     e.g., ৳500 off per month
        // ====================================================================
        $discountPercent = 0;
        $discountReason = null;
        $appliedDiscountId = null;
        $discountFlatAmount = 0; // For monthly: flat amount off per month

        // Priority 1: If a specific discount rule ID is provided, use that
        if ($discountId) {
            $discountRule = \Modules\Finance\app\Models\DiscountRule::find($discountId);
            if ($discountRule && $discountRule->status === 'active') {
                $discountValue = $discountRule->discount_value;
                if ($discountRule->discount_type === 'percentage') {
                    $discountPercent = $discountValue;
                    $discountReason = $discountRule->name;
                } elseif ($discountRule->discount_type === 'fixed') {
                    if ($feeType === 'monthly') {
                        // For monthly: fixed amount = flat discount per month (preferable)
                        $discountFlatAmount = $discountValue;
                        $discountPercent = ($totalFee > 0) ? round(($discountValue / $totalFee) * 100, 2) : 0;
                    } else {
                        // For one_time: fixed amount = flat discount off total
                        $discountPercent = ($totalFee > 0) ? round(($discountValue / $totalFee) * 100, 2) : 0;
                    }
                    $discountReason = $discountRule->name;
                }
                $appliedDiscountId = $discountId;

                // Apply max cap if set
                if ($discountRule->max_cap && $discountRule->max_cap > 0) {
                    if ($feeType === 'monthly' && $discountFlatAmount > 0) {
                        // For monthly flat discount: cap the flat amount
                        if ($discountFlatAmount > $discountRule->max_cap) {
                            $discountFlatAmount = $discountRule->max_cap;
                            $discountPercent = ($totalFee > 0) ? round(($discountRule->max_cap / $totalFee) * 100, 2) : 0;
                        }
                    } else {
                        $maxDiscountAmount = ($totalFee * $discountPercent) / 100;
                        if ($maxDiscountAmount > $discountRule->max_cap) {
                            $discountPercent = ($discountRule->max_cap / $totalFee) * 100;
                        }
                    }
                }
            }
        }
        // Priority 2: If manual discount percent is provided, use that
        elseif ($manualDiscountPercent !== null && $manualDiscountPercent > 0) {
            $discountPercent = $manualDiscountPercent;
            $discountReason = 'Manual Discount';
        }
        // Priority 3: Auto-calculate based on student eligibility
        elseif ($student) {
            $discounts = $this->calculateDiscount($student, $course);
            $discountPercent = $discounts['percent'];
            $discountReason = $discounts['reason'];
        }

        // Calculate discount amount
        if ($feeType === 'monthly' && $discountFlatAmount > 0) {
            // For monthly with flat discount: use the flat amount directly
            $discountAmount = $discountFlatAmount;
        } else {
            // For percentage-based or one_time: calculate as percentage of total
            $discountAmount = ($totalFee * $discountPercent) / 100;
        }
        $payableFee = max(0, $totalFee - $discountAmount);

        // Build monthly_breakdown for monthly fee type
        $monthlyBreakdown = [];
        if ($feeType === 'monthly') {
            foreach ($subjects as $sub) {
                $monthlyBreakdown[] = [
                    'id' => $sub['id'],
                    'name' => $sub['name'],
                    'monthly_fee' => $sub['monthly_fee'],
                ];
            }
        }

        // Enrollment fee is always separate from course fee (one-time or monthly)
        $dueAtEnrollment = round($enrollmentFee + $payableFee, 2);

        return [
            'subjects' => $subjects,
            'monthly_breakdown' => $monthlyBreakdown,
            'total_fee' => $totalFee,
            'enrollment_fee' => $enrollmentFee,
            'course_total_fee' => $totalFee,
            'course_payable_fee' => $payableFee,
            'due_at_enrollment' => $dueAtEnrollment,
            'minimum_due_at_enrollment' => round($enrollmentFee, 2),
            'discount_percent' => $discountPercent,
            'discount_reason' => $discountReason,
            'discount_amount' => $discountAmount,
            'discount_flat_amount' => $discountFlatAmount, // For monthly: flat amount off per month
            'payable_fee' => $payableFee,
            'fee_type' => $feeType,
            'applied_discount_id' => $appliedDiscountId,
        ];
    }

    /**
     * Calculate applicable discounts
     */
    public function calculateDiscount(Student $student, Course $course): array
    {
        $discounts = [];

        // Early Bird Discount (if enrolled before session starts)
        $currentSession = AcademicSession::where('is_current', true)->first();
        if ($currentSession && now()->lt($currentSession->start_date)) {
            $discounts[] = ['percent' => 10, 'reason' => 'Early Bird Discount'];
        }

        // Sibling Discount — match by guardian phone
        $siblingCount = 0;
        $guardian = $student->guardian;
        if ($guardian) {
            $phone = $guardian->guardian_phone ?? $guardian->father_phone ?? null;
            if ($phone) {
                $siblingCount = Enrollment::whereHas('student', function ($q) use ($student, $phone) {
                    $q->where('id', '!=', $student->id)
                        ->where(function ($sq) use ($phone) {
                            $sq->where('phone', $phone)
                                ->orWhereHas('guardian', function ($gq) use ($phone) {
                                    $gq->where('guardian_phone', $phone)
                                        ->orWhere('father_phone', $phone);
                                });
                        });
                })->where('status', 'active')->count();
            }
        }

        if ($siblingCount > 0) {
            $discounts[] = ['percent' => 10, 'reason' => 'Sibling Discount'];
        }

        // Loyalty Discount (renewal)
        $previousEnrollments = Enrollment::where('student_id', $student->id)
            ->where('status', 'completed')
            ->count();

        if ($previousEnrollments > 0) {
            $discounts[] = ['percent' => 5, 'reason' => 'Loyalty Discount (Renewal)'];
        }

        // Get the highest discount
        $bestDiscount = !empty($discounts) ? max(array_column($discounts, 'percent')) : 0;
        $bestReason = !empty($discounts) ? collect($discounts)->firstWhere('percent', $bestDiscount)['reason'] : null;

        return [
            'percent' => $bestDiscount,
            'reason' => $bestReason,
        ];
    }

    /**
     * Enroll a student in a course/batch
     */
    public function enroll(Student $student, array $data): Enrollment
    {
        return DB::transaction(function () use ($student, $data) {
            $batch = Batch::findOrFail($data['batch_id']);
            $feeType = $data['fee_type'] ?? 'one_time';

            // Validate batch belongs to the course (if course context exists)
            if (!empty($data['course_id']) && $batch->course_id !== $data['course_id']) {
                throw new \Exception('Batch does not belong to the selected course.');
            }

            // Check batch status — only allow enrollment in open or upcoming batches
            if (!in_array($batch->status, ['open', 'upcoming'])) {
                throw new \Exception('Batch is not open for enrollment. Current status: ' . $batch->status);
            }

            // Check seat availability
            if (!$batch->hasAvailableSeats()) {
                throw new \Exception('Batch is full. No available seats.');
            }

            // Calculate fee based on fee type (with optional discount)
            $feeCalculation = $this->calculateFee(
                $batch->course,
                $data['subject_ids'] ?? [],
                $student,
                $feeType,
                $data['discount_id'] ?? null,
                $data['discount_percent'] ?? null
            );

            // Generate enrollment number: CMS-2026-0001
            // Uses a retry loop to handle race conditions safely
            $year = now()->format('Y');
            $maxAttempts = 10;
            $enrollmentNo = null;

            for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
                $lastEnrollment = Enrollment::whereYear('created_at', $year)
                    ->orderBy('created_at', 'desc')
                    ->orderBy('enrollment_no', 'desc')
                    ->first();

                $sequence = $lastEnrollment
                    ? (int) substr($lastEnrollment->enrollment_no, -4) + 1
                    : 1;

                $candidateNo = 'CMS-' . $year . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);

                // Check if this number already exists (race condition guard)
                $exists = Enrollment::where('enrollment_no', $candidateNo)->exists();
                if (!$exists) {
                    $enrollmentNo = $candidateNo;
                    break;
                }
            }

            if (!$enrollmentNo) {
                // Fallback: use timestamp-based unique number
                $enrollmentNo = 'CMS-' . $year . '-' . now()->format('His') . str_pad(random_int(0, 99), 2, '0', STR_PAD_LEFT);
            }

            // Get current session
            $session = AcademicSession::where('is_current', true)->first();

            // For monthly fee type:
            // - total_fee = original_monthly_fee × duration_months (total course fee BEFORE discount)
            // - payable_fee = discounted_monthly_fee × duration_months (total course fee AFTER discount)
            // - paid_amount = what they paid at enrollment
            // Discount can be percentage or flat amount per month (flat is preferable for monthly)
            // The discount applies EVERY month, so payable_fee reflects the discounted total.
            // After generating monthly fee records, the initial payment is applied
            // to the first month's record automatically
            $originalMonthlyFee = $feeCalculation['total_fee']; // Sum of all subject monthly_fees (undiscounted per month)
            $discountedMonthlyFee = $feeCalculation['payable_fee']; // Monthly fee after discount (per month)
            $enrollmentFee = (float) ($feeCalculation['enrollment_fee'] ?? $batch->course->enrollment_fee ?? 0);
            $totalPaidAmount = (float) ($data['paid_amount'] ?? 0);
            $enrollmentFeePaid = min($totalPaidAmount, $enrollmentFee);
            $courseFeePaid = max(0, $totalPaidAmount - $enrollmentFeePaid);

            // Calculate total course fee for monthly type
            $durationMonths = $batch->course->duration_days
                ? max(1, ceil($batch->course->duration_days / 30))
                : 12;

            // total_fee = original monthly fee × duration (undiscounted total — reflects the full course value)
            // payable_fee = discounted monthly fee × duration (what the student actually needs to pay total)
            // The discount amount = total_fee - payable_fee
            $totalUndiscountedCourseFee = $originalMonthlyFee * $durationMonths;
            $totalDiscountedCourseFee = $discountedMonthlyFee * $durationMonths;

            // For monthly fee: payable_fee is the total discounted course fee (all months)
            // For one_time: payable_fee is the total fee with discount applied
            $payableFee = $feeType === 'monthly' ? $totalDiscountedCourseFee : $feeCalculation['payable_fee'];
            $totalFee = $feeType === 'monthly' ? $totalUndiscountedCourseFee : $feeCalculation['total_fee'];

            // Create enrollment
            $enrollment = Enrollment::create([
                'enrollment_no' => $enrollmentNo,
                'student_id' => $student->id,
                'batch_id' => $batch->id,
                'academic_session_id' => $data['academic_session_id'] ?? $session?->id,
                'enrollment_type' => $data['enrollment_type'] ?? 'new',
                'source' => $data['source'] ?? 'admin',
                'previous_enrollment_id' => $data['previous_enrollment_id'] ?? null,
                'enrolled_class_id' => $data['enrolled_class_id'] ?? $batch->course->class_id,
                'enrolled_group_id' => $data['enrolled_group_id'] ?? $batch->course->group_id,
                'mode' => $batch->mode,
                'total_fee' => $totalFee,
                'discount_percent' => $feeCalculation['discount_percent'],
                'discount_reason' => $feeCalculation['discount_reason'],
                'payable_fee' => $payableFee,
                'fee_type' => $feeType,
                'enrollment_fee' => $enrollmentFee,
                'enrollment_fee_paid' => $enrollmentFeePaid,
                'paid_amount' => $courseFeePaid,
                'due_amount' => max(0, $payableFee - $courseFeePaid),
                'payment_status' => $this->determinePaymentStatus($courseFeePaid, $payableFee),
                'status' => $this->determineEnrollmentStatusByEnrollmentFee($enrollmentFeePaid, $enrollmentFee),
                'payment_method' => $data['payment_method'] ?? null,
                'payment_transaction_id' => $data['payment_transaction_id'] ?? null,
                'enrolled_at' => now(),
                'start_date' => $data['start_date'] ?? now(),
                'end_date' => $data['end_date'] ?? null,
                'guardian_phone' => $data['guardian_phone'] ?? $student->guardian?->phone,
                'guardian_email' => $data['guardian_email'] ?? $student->guardian?->email,
            ]);

            // Attach subjects with proper teacher/batch mapping
            if (!empty($data['subject_ids'])) {
                $subjectData = [];
                $teacherMap = !empty($data['subject_teachers']) ? $data['subject_teachers'] : [];
                $batchMap = !empty($data['subject_batches']) ? $data['subject_batches'] : [];

                foreach ($data['subject_ids'] as $subjectId) {
                    $courseSubject = $batch->course->subjects()
                        ->where('subjects.id', $subjectId)
                        ->first();

                    $subjectData[$subjectId] = [
                        'subject_fee' => $feeType === 'monthly'
                            ? ($courseSubject?->pivot?->monthly_fee ?? 0)
                            : ($courseSubject?->pivot?->fee ?? 0),
                        'teacher_id' => $teacherMap[$subjectId] ?? null,
                        'batch_id' => $batchMap[$subjectId] ?? null,
                    ];
                }
                $enrollment->subjects()->attach($subjectData);
            }

            // If monthly fee type, generate monthly fee records and apply initial payment
            if ($feeType === 'monthly') {
                $monthlyFeeService = app(MonthlyFeeService::class);
                $monthlyDiscountPercent = $feeCalculation['discount_percent'] ?? 0;
                $monthlyDiscountFlat = $feeCalculation['discount_flat_amount'] ?? 0;

                // Generate monthly fee records with discount info
                $monthlyFeeService->generateMonthlyFeeRecords(
                    $enrollment,
                    $monthlyDiscountPercent,
                    $monthlyDiscountFlat
                );

                // Apply initial course-fee payment to the first month's monthly fee record
                if ($courseFeePaid > 0) {
                    $firstRecord = \Modules\Enrollment\app\Models\MonthlyFeeRecord::where('enrollment_id', $enrollment->id)
                        ->orderBy('month', 'asc')
                        ->first();

                    if ($firstRecord) {
                        $monthlyFeeService->recordPayment(
                            $firstRecord->id,
                            min($courseFeePaid, $firstRecord->due_amount),
                            $data['payment_method'] ?? 'cash',
                            $data['payment_transaction_id'] ?? null,
                            $data['payment_reference'] ?? null,
                            'Initial enrollment payment',
                            null, null, null,
                            $data['created_by'] ?? null
                        );
                    }
                }

                // Recalculate enrollment-level course payment status from monthly fee records
                $allRecords = \Modules\Enrollment\app\Models\MonthlyFeeRecord::where('enrollment_id', $enrollment->id)
                    ->orderBy('month', 'asc')->get();
                $totalCoursePaid = $allRecords->sum('paid_amount');
                $firstMonth = $allRecords->first();
                $isFirstMonthPaid = $firstMonth && $firstMonth->payment_status === 'paid';

                $enrollment->update([
                    'paid_amount' => $totalCoursePaid,
                    'due_amount' => max(0, $payableFee - $totalCoursePaid),
                    'paid_months' => $allRecords->where('payment_status', 'paid')->count(),
                    'payment_status' => $isFirstMonthPaid ? 'paid' : $this->determinePaymentStatus($totalCoursePaid, $payableFee),
                    'status' => $this->determineEnrollmentStatusByEnrollmentFee($enrollmentFeePaid, $enrollmentFee),
                ]);
            }

            // Increment batch enrolled count
            $batch->increment('enrolled_count');

            // Auto-update batch status if full
            if (!$batch->hasAvailableSeats()) {
                $batch->update(['status' => 'full']);
            }

            // Create user account for the student only if a password was explicitly provided
            if (!empty($data['password']) && !$student->user_id) {
                $this->createStudentUserAccount(
                    $student,
                    $data['username'] ?? null,
                    $data['password']
                );
            }

            return $enrollment->load(['student', 'batch.course', 'subjects']);
        });
    }

    /**
     * Renew an existing student's enrollment
     */
    public function renew(Student $student, array $data): Enrollment
    {
        // Find previous active enrollment
        $previousEnrollment = Enrollment::where('student_id', $student->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        if ($previousEnrollment) {
            // Mark previous as completed
            $previousEnrollment->update([
                'status' => 'completed',
                'end_date' => now(),
            ]);
        }

        $data['enrollment_type'] = 'renewal';
        $data['previous_enrollment_id'] = $previousEnrollment?->id;

        return $this->enroll($student, $data);
    }

    /**
     * Transfer student to a different batch
     */
    public function transferBatch(Enrollment $enrollment, string $newBatchId): Enrollment
    {
        return DB::transaction(function () use ($enrollment, $newBatchId) {
            $newBatch = Batch::findOrFail($newBatchId);

            if (!$newBatch->hasAvailableSeats()) {
                throw new \Exception('Target batch is full.');
            }

            $oldBatch = $enrollment->batch;

            // Update enrollment
            $enrollment->update(['batch_id' => $newBatchId]);

            // Update batch counts
            $oldBatch->decrement('enrolled_count');
            $newBatch->increment('enrolled_count');

            // Update batch statuses
            if ($oldBatch->status === 'full') {
                $oldBatch->update(['status' => 'open']);
            }
            if (!$newBatch->hasAvailableSeats()) {
                $newBatch->update(['status' => 'full']);
            }

            return $enrollment->fresh()->load(['batch.course', 'student']);
        });
    }

    /**
     * Mark enrollment as dropped
     */
    public function dropout(Enrollment $enrollment, string $reason = null): Enrollment
    {
        return DB::transaction(function () use ($enrollment, $reason) {
            $enrollment->update([
                'status' => 'dropped',
                'end_date' => now(),
            ]);

            // Free up batch seat
            $batch = $enrollment->batch;
            $batch->decrement('enrolled_count');

            if ($batch->status === 'full') {
                $batch->update(['status' => 'open']);
            }

            return $enrollment->fresh();
        });
    }

    /**
     * Get enrollment statistics
     */
    public function getStats(): array
    {
        $totalEnrollments = Enrollment::count();
        $activeEnrollments = Enrollment::where('status', 'active')->count();
        $pendingEnrollments = Enrollment::where('status', 'pending')->count();
        $droppedEnrollments = Enrollment::where('status', 'dropped')->count();

        $totalRevenue = Enrollment::where('status', 'active')
            ->sum('paid_amount');

        $todayEnrollments = Enrollment::whereDate('created_at', today())->count();

        $courseStats = Course::withCount(['enrollments' => function ($q) {
            $q->where('status', 'active');
        }])->get()->map(function ($course) {
            return [
                'name' => $course->name,
                'category' => $course->category,
                'count' => $course->enrollments_count,
            ];
        });

        $modeStats = Enrollment::selectRaw('mode, count(*) as count')
            ->where('status', 'active')
            ->groupBy('mode')
            ->get();

        return [
            'total_enrollments' => $totalEnrollments,
            'active_enrollments' => $activeEnrollments,
            'pending_enrollments' => $pendingEnrollments,
            'dropped_enrollments' => $droppedEnrollments,
            'total_revenue' => $totalRevenue,
            'today_enrollments' => $todayEnrollments,
            'course_stats' => $courseStats,
            'mode_stats' => $modeStats,
        ];
    }

    /**
     * Determine payment status
     */
    private function determinePaymentStatus(float $paid, float $total): string
    {
        if ($paid <= 0) return 'pending';
        if ($paid >= $total) return 'paid';
        return 'partial';
    }

    /**
     * Determine enrollment status based on course fee payment (legacy).
     */
    private function determineEnrollmentStatus(float $paid, float $payable): string
    {
        if ($payable <= 0) return 'active';
        if ($paid >= $payable) return 'active';
        return 'pending';
    }

    /**
     * Enrollment status is gated by enrollment (admission) fee payment.
     */
    private function determineEnrollmentStatusByEnrollmentFee(float $enrollmentFeePaid, float $enrollmentFee): string
    {
        if ($enrollmentFee <= 0) {
            return 'active';
        }
        if ($enrollmentFeePaid >= $enrollmentFee) {
            return 'active';
        }
        return 'pending';
    }

    /**
     * Split a payment between enrollment fee and course fee.
     */
    private function splitPaymentAmount(Enrollment $enrollment, float $amount): array
    {
        $enrollmentFee = (float) ($enrollment->enrollment_fee ?? 0);
        $enrollmentFeePaid = (float) ($enrollment->enrollment_fee_paid ?? 0);
        $enrollmentFeeRemaining = max(0, $enrollmentFee - $enrollmentFeePaid);

        $toEnrollmentFee = min($amount, $enrollmentFeeRemaining);
        $toCourseFee = max(0, $amount - $toEnrollmentFee);

        return [
            'to_enrollment_fee' => $toEnrollmentFee,
            'to_course_fee' => $toCourseFee,
            'new_enrollment_fee_paid' => $enrollmentFeePaid + $toEnrollmentFee,
            'new_course_fee_paid' => (float) ($enrollment->paid_amount ?? 0) + $toCourseFee,
        ];
    }

    /**
     * Confirm a pending enrollment (e.g. after full payment received).
     */
    public function confirmEnrollment(Enrollment $enrollment): Enrollment
    {
        if ($enrollment->status !== 'pending') {
            throw new \Exception('Only pending enrollments can be confirmed.');
        }

        // Public (self) enrollments are admission requests: the admin's approval IS
        // the confirmation, regardless of whether the fee has been collected yet
        // (e.g. cash to be paid at the centre). Admin-created enrollments still
        // require the enrollment fee to be settled first.
        if ($enrollment->source !== 'public') {
            $enrollmentFee = (float) ($enrollment->enrollment_fee ?? 0);
            $enrollmentFeePaid = (float) ($enrollment->enrollment_fee_paid ?? 0);
            if ($enrollmentFee > 0 && $enrollmentFeePaid < $enrollmentFee) {
                throw new \Exception('Enrollment fee must be paid before confirming enrollment.');
            }
        }

        $enrollment->update([
            'status' => 'active',
            'enrolled_at' => $enrollment->enrolled_at ?? now(),
        ]);

        return $enrollment->fresh();
    }

    /**
     * Record payment with automatic status update.
     * When payment reaches full amount, enrollment auto-activates.
     */
    public function recordPayment(Enrollment $enrollment, float $amount, ?string $method = null, ?string $reference = null, ?string $transactionId = null): Enrollment
    {
        return DB::transaction(function () use ($enrollment, $amount, $method, $reference, $transactionId) {
            $split = $this->splitPaymentAmount($enrollment, $amount);
            $newCoursePaid = $split['new_course_fee_paid'];
            $newDue = max(0, (float) $enrollment->payable_fee - $newCoursePaid);
            $paymentStatus = $this->determinePaymentStatus($newCoursePaid, (float) $enrollment->payable_fee);
            $status = $this->determineEnrollmentStatusByEnrollmentFee(
                $split['new_enrollment_fee_paid'],
                (float) ($enrollment->enrollment_fee ?? 0)
            );

            $enrollment->update([
                'enrollment_fee_paid' => $split['new_enrollment_fee_paid'],
                'paid_amount' => $newCoursePaid,
                'due_amount' => $newDue,
                'payment_status' => $paymentStatus,
                'status' => $status,
                'payment_method' => $method ?? $enrollment->payment_method,
                'payment_transaction_id' => $transactionId ?? $enrollment->payment_transaction_id,
                'enrolled_at' => $status === 'active' && !$enrollment->enrolled_at ? now() : $enrollment->enrolled_at,
            ]);

            return $enrollment->fresh();
        });
    }

    /**
     * Search student by phone, name, or student ID.
     */
    public function searchStudent(string $query): array
    {
        $students = Student::with(['guardian', 'currentClass', 'currentSection'])
            ->where(function ($q) use ($query) {
                $q->where('phone', 'like', "%{$query}%")
                    ->orWhere('first_name', 'like', "%{$query}%")
                    ->orWhere('last_name', 'like', "%{$query}%")
                    ->orWhere('student_id', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->limit(10)
            ->get();

        $results = $students->toArray();

        // Attach enrollment info (course name, batch name) to each student result
        foreach ($results as &$studentData) {
            $enrollments = \Modules\Enrollment\app\Models\Enrollment::with('batch.course')
                ->where('student_id', $studentData['id'])
                ->get();

            $studentData['enrollments'] = $enrollments->toArray();

            // Set the first active enrollment's course/batch as defaults
            $activeEnrollment = $enrollments->first(function ($e) {
                return !in_array($e->status, ['dropped_out', 'completed', 'cancelled']);
            }) ?? $enrollments->first();

            if ($activeEnrollment) {
                $studentData['course_name'] = $activeEnrollment->batch?->course?->name;
                $studentData['course'] = $activeEnrollment->batch?->course?->name;
                $studentData['batch_name'] = $activeEnrollment->batch?->name;
                $studentData['batch'] = $activeEnrollment->batch?->name;
            }
        }

        return $results;
    }

    /**
     * Get suggested courses for a student based on their class/group.
     */
    public function getSuggestedCourses(?string $classId = null, ?int $groupId = null, ?string $target = null): array
    {
        $courses = collect();

        // Load academic courses (filtered by class if provided, ALL if not)
        $academicQuery = Course::with(['subjects', 'class', 'group', 'batches' => function ($q) {
            $q->whereIn('status', ['open', 'upcoming'])->with(['teacher', 'room']);
        }])
            ->where('category', 'academic')
            ->active()
            ->orderBy('sort_order');

        if ($classId) {
            $academicQuery->where('class_id', $classId);
        }

        $academicCourses = $academicQuery->get();

        // If group is specified, filter further
        if ($groupId) {
            $academicCourses = $academicCourses->filter(function ($course) use ($groupId) {
                return !$course->group_id || $course->group_id == $groupId;
            });
        }

        $courses = $courses->concat($academicCourses);

        // Always include admission coaching courses (filtered by target if provided)
        $admissionQuery = Course::with(['subjects', 'batches' => function ($q) {
            $q->whereIn('status', ['open', 'upcoming'])->with(['teacher', 'room']);
        }])
            ->where('category', 'admission_coaching')
            ->active()
            ->orderBy('sort_order');

        if ($target) {
            $admissionQuery->where('target', $target);
        }

        $courses = $courses->concat($admissionQuery->get());

        // For each course, attach batch summary
        return $courses->map(function ($course) {
            return [
                'id' => $course->id,
                'name' => $course->name,
                'code' => $course->code,
                'category' => $course->category,
                'class' => $course->class?->only(['id', 'name']),
                'group' => $course->group?->only(['id', 'name']),
                'target' => $course->target,
                'duration_label' => $course->duration_label,
                'duration_days' => $course->duration_days,
                'one_time_fee' => (float) ($course->one_time_fee ?? 0),
                'enrollment_fee' => (float) ($course->enrollment_fee ?? 0),
                'has_online' => $course->has_online,
                'has_offline' => $course->has_offline,
                'is_featured' => $course->is_featured,
                'description' => $course->short_description,
                'cover_image' => $course->cover_image,
                'subjects' => $course->subjects->map(function ($s) {
                    return [
                        'id' => $s->id,
                        'name' => $s->name,
                        'fee' => $s->pivot->fee ?? 0,
                        'monthly_fee' => $s->pivot->monthly_fee ?? 0,
                        'is_mandatory' => (bool)($s->pivot->is_mandatory ?? false),
                        'is_optional' => (bool)($s->pivot->is_optional ?? false),
                    ];
                }),
                'batch_summary' => [
                    'total' => $course->batches->count(),
                    'open' => $course->batches->where('status', 'open')->count(),
                    'online' => $course->batches->where('mode', 'online')->count(),
                    'offline' => $course->batches->where('mode', 'offline')->count(),
                    'hybrid' => $course->batches->where('mode', 'hybrid')->count(),
                ],
            ];
        })->values()->toArray();
    }

    /**
     * Get suggested batches for a course with waitlist awareness.
     */
    public function getSuggestedBatches(string $courseId, ?string $mode = null): array
    {
        $course = Course::findOrFail($courseId);
        $query = $course->batches()->with(['teacher', 'room', 'academicSession']);

        // Only show open or upcoming batches for enrollment
        $query->whereIn('status', ['open', 'upcoming']);

        if ($mode) {
            $query->where('mode', $mode);
        }

        return $query->orderBy('created_at', 'desc')->get()->map(function ($batch) {
            $available = $batch->availableSeats();
            $isFull = $available <= 0;

            return [
                'id' => $batch->id,
                'name' => $batch->name,
                'code' => $batch->code,
                'mode' => $batch->mode,
                'status' => $batch->status,
                'capacity' => $batch->capacity,
                'enrolled_count' => $batch->enrolled_count,
                'available_seats' => $available,
                'is_full' => $isFull,
                'seat_percentage' => $batch->capacity > 0 ? round(($batch->enrolled_count / $batch->capacity) * 100) : 0,
                'days' => $batch->days,
                'start_time' => $batch->start_time,
                'end_time' => $batch->end_time,
                'teacher' => $batch->teacher?->only(['id', 'first_name', 'last_name']),
                'room' => $batch->room?->only(['id', 'name', 'capacity']),
                'platform' => $batch->platform,
                'meeting_link' => $batch->meeting_link,
                'campus_location' => $batch->campus_location,
                'academic_session' => $batch->academicSession?->only(['id', 'name']),
            ];
        })->toArray();
    }

    /**
     * Check for sibling discount based on guardian phone match.
     */
    public function checkSiblingDiscount(Student $student): array
    {
        $guardianPhone = $student->guardian?->guardian_phone
            ?? $student->guardian?->father_phone
            ?? $student->phone;

        if (!$guardianPhone) {
            return ['has_sibling' => false, 'discount_percent' => 0, 'sibling_names' => []];
        }

        // Find other active students with same guardian phone
        $siblings = Student::where('id', '!=', $student->id)
            ->where(function ($q) use ($guardianPhone) {
                $q->where('phone', $guardianPhone)
                    ->orWhereHas('guardian', function ($gq) use ($guardianPhone) {
                        $gq->where('guardian_phone', $guardianPhone)
                            ->orWhere('father_phone', $guardianPhone);
                    });
            })
            ->whereHas('enrollments', function ($eq) {
                $eq->where('status', 'active');
            })
            ->get();

        if ($siblings->isEmpty()) {
            return ['has_sibling' => false, 'discount_percent' => 0, 'sibling_names' => []];
        }

        return [
            'has_sibling' => true,
            'discount_percent' => 10,
            'sibling_names' => $siblings->pluck('first_name')->toArray(),
        ];
    }

    /**
     * Check for duplicate enrollment (same student, same course, active/pending).
     */
    public function checkDuplicateEnrollment(string $studentId, string $courseId): ?Enrollment
    {
        return Enrollment::where('student_id', $studentId)
            ->whereHas('batch', function ($q) use ($courseId) {
                $q->where('course_id', $courseId);
            })
            ->whereIn('status', ['pending', 'active'])
            ->first();
    }

    /**
     * Generate student ID for new students.
     * Format: STU-2026-000245
     * Retries with next sequence if the generated ID already exists (race condition guard).
     */
    public function generateStudentId(): string
    {
        $year = now()->format('Y');
        $maxAttempts = 10;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            // Get the highest sequence number currently used this year
            $lastStudent = \Modules\Student\app\Models\Student::whereYear('created_at', $year)
                ->orderBy('student_id', 'desc')
                ->first();

            $sequence = $lastStudent
                ? (int) substr($lastStudent->student_id ?? '000000', -6) + 1
                : 1;

            $studentId = 'STU-' . $year . '-' . str_pad($sequence, 6, '0', STR_PAD_LEFT);

            // Check if this ID already exists including soft-deleted records (withTrashed)
            // because the DB unique constraint applies to all rows including soft-deleted ones
            $exists = \Modules\Student\app\Models\Student::withTrashed()
                ->where('student_id', $studentId)
                ->exists();

            if (!$exists) {
                return $studentId;
            }
        }

        // Fallback: use timestamp-based ID if all attempts exhausted (extremely unlikely)
        return 'STU-' . $year . '-' . now()->format('His');
    }

    /**
     * Add student to waiting list for a full batch.
     */
    public function addToWaitingList(string $studentId, string $batchId, ?string $remarks = null): array
    {
        $batch = Batch::findOrFail($batchId);
        $student = \Modules\Student\app\Models\Student::findOrFail($studentId);

        // Count existing waiting entries for this batch
        $position = Enrollment::where('batch_id', $batchId)
            ->where('status', 'waiting')
            ->count() + 1;

        $enrollment = Enrollment::create([
            'enrollment_no' => 'WL-' . now()->format('Ymd') . '-' . str_pad($position, 3, '0', STR_PAD_LEFT),
            'student_id' => $studentId,
            'batch_id' => $batchId,
            'mode' => $batch->mode,
            'status' => 'waiting',
            'payable_fee' => 0,
            'paid_amount' => 0,
            'due_amount' => 0,
            'payment_status' => 'pending',
        ]);

        return [
            'enrollment' => $enrollment,
            'waiting_position' => $position,
        ];
    }

    /**
     * Approve waiting list enrollment (move from waiting → pending).
     */
    public function approveFromWaitingList(string $enrollmentId): Enrollment
    {
        return DB::transaction(function () use ($enrollmentId) {
            $enrollment = Enrollment::findOrFail($enrollmentId);

            if ($enrollment->status !== 'waiting') {
                throw new \Exception('Only waiting list enrollments can be approved.');
            }

            $batch = $enrollment->batch;
            if (!$batch->hasAvailableSeats()) {
                throw new \Exception('Batch still has no available seats.');
            }

            // Generate proper enrollment number
            $year = now()->format('Y');
            $lastEnrollment = Enrollment::whereYear('created_at', $year)
                ->where('status', '!=', 'waiting')
                ->orderBy('id', 'desc')
                ->first();

            $sequence = $lastEnrollment
                ? (int) substr($lastEnrollment->enrollment_no, -4) + 1
                : 1;

            $enrollmentNo = 'CMS-' . $year . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);

            $enrollment->update([
                'enrollment_no' => $enrollmentNo,
                'status' => 'pending',
                'enrolled_at' => now(),
            ]);

            $batch->increment('enrolled_count');
            if (!$batch->hasAvailableSeats()) {
                $batch->update(['status' => 'full']);
            }

            return $enrollment->fresh();
        });
    }

    /**
     * Get pending enrollments with student & batch info.
     */
    public function getPendingEnrollments(array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = Enrollment::with(['student:id,first_name,last_name,phone,student_id', 'batch.course:id,name'])
            ->where('status', 'pending')
            ->where('payment_status', '!=', 'paid');

        if (!empty($filters['batch_id'])) {
            $query->where('batch_id', $filters['batch_id']);
        }

        if (!empty($filters['course_id'])) {
            $query->whereHas('batch', function ($q) use ($filters) {
                $q->where('course_id', $filters['course_id']);
            });
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('enrollment_no', 'like', "%{$search}%")
                    ->orWhereHas('student', function ($sq) use ($search) {
                        $sq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get waiting list enrollments.
     */
    public function getWaitingList(array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = Enrollment::with(['student:id,first_name,last_name,phone', 'batch.course:id,name'])
            ->where('status', 'waiting');

        if (!empty($filters['batch_id'])) {
            $query->where('batch_id', $filters['batch_id']);
        }

        return $query->orderBy('created_at', 'asc')->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Create a user account for a student with the provided credentials.
     * Only called when the admin explicitly sets a password during enrollment.
     */
    private function createStudentUserAccount(Student $student, ?string $username, string $password): void
    {
        // Skip if student already has a user account
        if ($student->user_id) {
            return;
        }

        $fullName = trim($student->first_name . ' ' . $student->last_name);
        $email = $student->email ?: $student->student_id . '@student.local';
        $phone = $student->phone;

        // Use provided username or fallback to student_id
        $userName = $username ?: $student->student_id;

        // Check if a user with this email already exists
        $existingUser = User::where('email', $email)->first();
        if ($existingUser) {
            // Link existing user to student
            $student->update(['user_id' => $existingUser->id]);
            return;
        }

        // Create the user
        $user = User::create([
            'name'     => $userName,
            'email'    => $email,
            'phone'    => $phone,
            'password' => Hash::make($password),
        ]);

        // Assign student role
        $user->assignRole('student');

        // Link student record to the newly created user
        $student->update(['user_id' => $user->id]);
    }

    /**
     * Provision (or activate) a student's login account.
     *
     * - If the student has no user yet, create one with the given/auto credentials
     *   and the requested status ('active' or 'inactive').
     * - If the student already has a user, optionally promote it to the requested
     *   status (e.g. activate an 'inactive' account on admin approval).
     *
     * @return array{username:string, password:?string, created:bool, activated:bool}
     */
    public function provisionStudentLogin(Student $student, ?string $username = null, ?string $password = null, string $status = 'active'): array
    {
        $student->refresh();

        if ($student->user_id) {
            $user = User::find($student->user_id);
            $activated = false;
            $revealPassword = null;

            if ($user) {
                // Honor explicitly-provided credentials even for an existing (e.g.
                // auto-created) account, so the username/password the applicant typed
                // actually takes effect instead of silently keeping the old name.
                if ($username && $username !== $user->name) {
                    $clash = User::where('name', $username)->where('id', '!=', $user->id)->exists();
                    if (!$clash) {
                        $user->name = $username;
                    }
                }
                if ($password) {
                    $user->password = Hash::make($password);
                    $revealPassword = $password;
                }
                if ($status === 'active' && $user->status !== 'active') {
                    $user->status = 'active';
                    $activated = true;
                }
                if ($user->isDirty()) {
                    $user->save();
                }
            }

            return [
                'username' => $user?->name ?? $student->student_id,
                'password' => $revealPassword,
                'created' => false,
                'activated' => $activated,
            ];
        }

        $plainPassword = $password ?: (strtoupper(Str::random(2)) . random_int(1000, 9999));
        $userName = $username ?: $student->student_id;
        $email = $student->email ?: ($student->student_id . '@student.local');

        // Avoid clashing with an existing user (by email or username).
        $existing = User::where('email', $email)->orWhere('name', $userName)->first();
        if ($existing) {
            $student->update(['user_id' => $existing->id]);
            if ($status === 'active' && $existing->status !== 'active') {
                $existing->update(['status' => 'active']);
            }
            return ['username' => $existing->name, 'password' => null, 'created' => false, 'activated' => true];
        }

        $user = User::create([
            'name' => $userName,
            'email' => $email,
            'phone' => $student->phone,
            'password' => Hash::make($plainPassword),
            'status' => $status,
        ]);
        $user->assignRole('student');
        $student->update(['user_id' => $user->id]);

        return [
            'username' => $userName,
            'password' => $plainPassword,
            'created' => true,
            'activated' => $status === 'active',
        ];
    }

    /**
     * Backward-compatible wrapper — issues an active login account.
     */
    public function issueStudentCredentials(Student $student, ?string $username = null, ?string $password = null): array
    {
        return $this->provisionStudentLogin($student, $username, $password, 'active');
    }
}
