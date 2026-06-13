<?php

namespace Modules\Student\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Core\app\Services\GradingService;
use Modules\Student\app\Models\Student;
use Modules\Student\app\Models\StudentLeave;
use Modules\Enrollment\app\Models\Enrollment;
use Modules\Enrollment\app\Models\MonthlyFeeRecord;
use Modules\Enrollment\app\Models\MonthlyFeePayment;
use Modules\Exam\app\Models\Exam;
use Modules\Exam\app\Models\ExamResult;
use Modules\Exam\app\Models\ExamRoutine;
use Modules\Academic\app\Models\ClassRoutine;
use Modules\Communication\app\Services\NoticeBoardService;
use Modules\Cms\app\Services\DownloadResourceService;
use Modules\Cms\app\Services\StudyMaterialService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\app\Services\PortalAttendanceService;
use Modules\Exam\app\Services\ExamEligibilityService;

class StudentPortalController extends BaseApiController
{
    public function __construct(
        protected PortalAttendanceService $portalAttendance,
        protected GradingService $gradingService,
        protected ExamEligibilityService $examEligibility,
        protected NoticeBoardService $noticeBoardService,
        protected StudyMaterialService $studyMaterialService,
        protected DownloadResourceService $downloadResourceService,
    ) {}

    /**
     * Get the authenticated student record.
     * Tries multiple strategies to find the student linked to the logged-in user.
     * When found via fallback, automatically links the student to the user for future lookups.
     */
    private function getAuthenticatedStudent(): ?Student
    {
        $user = auth()->user();

        // -------------------------------------------------------
        // Helper: check if the user's name loosely matches the student's full name
        // (case-insensitive, partial match — e.g. user "Afridi" matches student "Afridi Gazi")
        // -------------------------------------------------------
        $nameMatches = function (Student $student) use ($user): bool {
            if (empty($user->name)) {
                return false;
            }
            $userName = mb_strtolower(trim($user->name));
            $studentName = mb_strtolower(trim($student->first_name . ' ' . ($student->last_name ?? '')));
            // Exact match on full name
            if ($studentName === $userName) {
                return true;
            }
            // User name is contained in student name (e.g. "afridi" in "afridi gazi")
            if (str_contains($studentName, $userName)) {
                return true;
            }
            // Student name is contained in user name
            if (str_contains($userName, $studentName)) {
                return true;
            }
            return false;
        };

        // -------------------------------------------------------
        // Strategy 1: Find by user_id (most reliable when correctly set)
        // BUT verify the name also matches to prevent cross-mapping bugs
        // -------------------------------------------------------
        $studentByUserId = Student::where('user_id', $user->id)->first();
        if ($studentByUserId) {
            // If the name also matches, this is definitely the right student
            if ($nameMatches($studentByUserId)) {
                return $studentByUserId;
            }
            // Name doesn't match — the user_id mapping is likely incorrect.
            // Clear the bad mapping so it doesn't cause issues, and fall through
            // to other strategies.
            $studentByUserId->update(['user_id' => null]);
        }

        // -------------------------------------------------------
        // Strategy 2: Find by matching email (only if unique)
        // -------------------------------------------------------
        if (!empty($user->email)) {
            $studentsByEmail = Student::where('email', $user->email)->get();
            if ($studentsByEmail->count() === 1) {
                $student = $studentsByEmail->first();
                // Verify name match to prevent cross-matching
                if ($nameMatches($student)) {
                    $student->update(['user_id' => $user->id]);
                    return $student;
                }
            }
        }

        // -------------------------------------------------------
        // Strategy 3: Find by matching phone number (only if unique)
        // -------------------------------------------------------
        if (!empty($user->phone)) {
            $studentsByPhone = Student::where('phone', $user->phone)->get();
            if ($studentsByPhone->count() === 1) {
                $student = $studentsByPhone->first();
                // Verify name match to prevent cross-matching
                if ($nameMatches($student)) {
                    $student->update(['user_id' => $user->id]);
                    return $student;
                }
            }
        }

        // -------------------------------------------------------
        // Strategy 4: Find by student_id matching the user's name (username)
        // e.g., user name "STU-2026-00001" matches student_id "STU-2026-00001"
        // -------------------------------------------------------
        if (!empty($user->name)) {
            $student = Student::where('student_id', $user->name)->first();
            if ($student) {
                $student->update(['user_id' => $user->id]);
                return $student;
            }
        }

        // -------------------------------------------------------
        // Strategy 5: Fuzzy name match — find a student whose full name
        // contains the user's name (case-insensitive)
        // -------------------------------------------------------
        if (!empty($user->name)) {
            $userNameLower = mb_strtolower(trim($user->name));
            // Search for students whose first_name or last_name contains the user name
            $student = Student::whereRaw('LOWER(first_name) LIKE ?', ['%' . $userNameLower . '%'])
                ->orWhereRaw('LOWER(last_name) LIKE ?', ['%' . $userNameLower . '%'])
                ->orWhereRaw("CONCAT(LOWER(first_name), ' ', LOWER(last_name)) LIKE ?", ['%' . $userNameLower . '%'])
                ->first();
            if ($student) {
                // Only use this match if it's unambiguous (only one student matches)
                $matchCount = Student::whereRaw('LOWER(first_name) LIKE ?', ['%' . $userNameLower . '%'])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', ['%' . $userNameLower . '%'])
                    ->orWhereRaw("CONCAT(LOWER(first_name), ' ', LOWER(last_name)) LIKE ?", ['%' . $userNameLower . '%'])
                    ->count();
                if ($matchCount === 1) {
                    $student->update(['user_id' => $user->id]);
                    return $student;
                }
            }
        }

        // -------------------------------------------------------
        // Strategy 6 (LAST RESORT): Match by email without name verification
        // Only used when the email is unique to one student, even if names don't match.
        // This handles cases where the user's login name differs from the student's
        // registered name (e.g., user "rasel" but student "Mofazzal Hossain").
        // -------------------------------------------------------
        if (!empty($user->email)) {
            $studentsByEmail = Student::where('email', $user->email)->get();
            if ($studentsByEmail->count() === 1) {
                $student = $studentsByEmail->first();
                $student->update(['user_id' => $user->id]);
                return $student;
            }
        }

        // -------------------------------------------------------
        // Strategy 7 (LAST RESORT): Match by phone without name verification
        // Only used when the phone is unique to one student.
        // -------------------------------------------------------
        if (!empty($user->phone)) {
            $studentsByPhone = Student::where('phone', $user->phone)->get();
            if ($studentsByPhone->count() === 1) {
                $student = $studentsByPhone->first();
                $student->update(['user_id' => $user->id]);
                return $student;
            }
        }

        return null;
    }

    /**
     * Get the authenticated student's profile.
     */
    public function profile(): JsonResponse
    {
        $user = auth()->user();
        $student = $this->getAuthenticatedStudent();

        if (!$student) {
            return $this->notFound('Student profile not found. Please contact administration.');
        }

        // Load relations after finding the student
        $student->load(['currentClass', 'currentSection', 'guardian']);

        return $this->success([
            'user' => $user,
            'student' => $student,
        ]);
    }

    /**
     * Get student dashboard stats.
     */
    public function dashboard(): JsonResponse
    {
        $student = $this->getAuthenticatedStudent();

        if (!$student) {
            return $this->notFound('Student profile not found');
        }

        $enrollments = Enrollment::where('student_id', $student->id)
            ->with(['batch.course', 'batch.academicSession'])
            ->get();

        $activeEnrollments = $enrollments->where('status', 'active');

        // Fee stats
        $totalDue = MonthlyFeeRecord::whereIn('enrollment_id', $activeEnrollments->pluck('id'))
            ->where('payment_status', '!=', 'paid')
            ->sum('due_amount');

        $overdueCount = MonthlyFeeRecord::whereIn('enrollment_id', $activeEnrollments->pluck('id'))
            ->where('payment_status', '!=', 'paid')
            ->where('due_date', '<', now())
            ->count();

        // Upcoming exams — only batches the student is actively enrolled in
        $batchIds = $activeEnrollments->pluck('batch_id')->filter()->unique()->values()->toArray();

        $upcomingExams = collect();
        if (!empty($batchIds)) {
            $routineExamIds = ExamRoutine::published()
                ->whereIn('batch_id', $batchIds)
                ->distinct()
                ->pluck('exam_id');

            $upcomingExams = Exam::where('status', 'published')
                ->where('start_date', '>=', now())
                ->where(function ($q) use ($batchIds, $routineExamIds) {
                    $q->whereIn('batch_id', $batchIds);
                    if ($routineExamIds->isNotEmpty()) {
                        $q->orWhereIn('id', $routineExamIds);
                    }
                })
                ->orderBy('start_date')
                ->take(5)
                ->get();
        }

        // Recent notices
        $recentNotices = $this->noticeBoardService
            ->publishedQueryForUser(auth()->user())
            ->take(5)
            ->get();

        // Attendance stats from the new attendance_logs system
        $attendancePercentage = 0;
        $attendanceData = $this->portalAttendance->getPortalData('student', $student->id);
        $attendancePercentage = $attendanceData['percentage'] ?? 0;

        return $this->success([
            'student' => $student,
            'enrollments' => $enrollments,
            'active_enrollments_count' => $activeEnrollments->count(),
            'total_due' => (float) $totalDue,
            'overdue_count' => $overdueCount,
            'upcoming_exams' => $upcomingExams,
            'recent_notices' => $recentNotices,
            'attendance_percentage' => $attendancePercentage,
        ]);
    }

    /**
     * Get student's enrollments with fee info.
     */
    public function enrollments(): JsonResponse
    {
        $student = $this->getAuthenticatedStudent();

        if (!$student) {
            return $this->notFound('Student profile not found');
        }

        $enrollments = Enrollment::where('student_id', $student->id)
            ->with([
                'batch.course',
                'batch.academicSession',
                'batch.course.subjects',
                'monthlyFeeRecords' => function ($q) {
                    $q->orderBy('month', 'asc');
                },
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success($enrollments);
    }

    /**
     * Get fee dashboard for student.
     */
    public function feeDashboard(): JsonResponse
    {
        $student = $this->getAuthenticatedStudent();

        if (!$student) {
            return $this->notFound('Student profile not found');
        }

        $enrollments = Enrollment::where('student_id', $student->id)
            ->where('status', 'active')
            ->with(['batch.course'])
            ->get();

        $overallTotalFees = 0;
        $overallTotalPaid = 0;
        $overallTotalDue = 0;
        $overallTotalDiscount = 0;
        $overallOverdueCount = 0;
        $enrollmentData = [];

        foreach ($enrollments as $enrollment) {
            // Check if Smart Fee system has assignments for this enrollment
            $smartAssignments = \Modules\Finance\app\Models\StudentFeeAssignment::where('enrollment_id', $enrollment->id)
                ->with(['feeStructure.feeType'])
                ->get();

            // Also fetch legacy monthly fee records (they may exist even if smart assignments exist)
            $legacyMonthlyRecords = MonthlyFeeRecord::where('enrollment_id', $enrollment->id)->get();

            // Also fetch fee notifications (exam fees, event-based fees) that don't yet
            // have a corresponding StudentFeeAssignment record.
            $notifications = \Modules\Finance\app\Models\StudentFeeNotification::where('enrollment_id', $enrollment->id)
                ->whereIn('status', ['unread', 'read'])
                ->with(['feeStructure.feeType'])
                ->get();

            // Merge data from smart assignments, legacy monthly records, and notifications
            $allFeesAmount = 0;
            $allPaidAmount = 0;
            $allDueAmount = 0;
            $allDiscountAmount = 0;
            $allOverdueCount = 0;
            $allCategories = [];

            // Process smart assignments
            foreach ($smartAssignments as $a) {
                $feeAmount = $a->original_amount + ($a->late_fee_applied ?? 0);
                $paid = $a->paid_amount;
                $due = max(0, ($a->final_amount + ($a->late_fee_applied ?? 0)) - $paid);
                $discount = max(0, $a->original_amount - $a->final_amount);

                $allFeesAmount += $feeAmount;
                $allPaidAmount += $paid;
                $allDueAmount += $due;
                $allDiscountAmount += $discount;

                if ($a->status !== 'paid' && $a->due_date < now()) {
                    $allOverdueCount++;
                }

                $cat = $a->feeStructure?->feeType?->category ?? 'monthly';
                if (!in_array($cat, $allCategories)) {
                    $allCategories[] = $cat;
                }
            }

            // Process legacy monthly records (only if not already covered by smart assignments)
            $coveredMonths = [];
            foreach ($smartAssignments as $a) {
                if ($a->period_month) {
                    $coveredMonths[] = $a->period_month;
                }
            }

            foreach ($legacyMonthlyRecords as $r) {
                // Check if this month is already covered by a smart assignment
                $monthKey = $r->month; // e.g., "January 2026"
                // Try to match by period_month if available
                $monthDate = \Carbon\Carbon::parse($r->month);
                $periodKey = $monthDate->format('Y-m');

                if (in_array($periodKey, $coveredMonths)) {
                    continue; // Skip - already covered by smart assignment
                }

                $feeAmount = $r->total_monthly_fee ?? 0;
                $paid = $r->paid_amount ?? 0;
                $due = max(0, ($r->due_amount ?? 0) - $paid);
                $discount = max(0, $feeAmount - ($r->due_amount ?? 0));

                $allFeesAmount += $feeAmount;
                $allPaidAmount += $paid;
                $allDueAmount += $due;
                $allDiscountAmount += $discount;

                if ($r->payment_status !== 'paid' && $r->due_date && $r->due_date < now()) {
                    $allOverdueCount++;
                }

                if (!in_array('monthly', $allCategories)) {
                    $allCategories[] = 'monthly';
                }
            }

            // Process fee notifications (exam fees, event-based fees) that don't have
            // a corresponding StudentFeeAssignment record yet.
            foreach ($notifications as $notif) {
                // Skip if a StudentFeeAssignment already exists for this fee_structure + enrollment
                $existingAssignment = \Modules\Finance\app\Models\StudentFeeAssignment::where('enrollment_id', $enrollment->id)
                    ->where('fee_structure_id', $notif->fee_structure_id)
                    ->whereIn('status', ['pending', 'partial'])
                    ->exists();

                if ($existingAssignment) {
                    continue; // Already covered by smart assignment above
                }

                $amount = (float) ($notif->amount ?? 0);
                $allFeesAmount += $amount;
                $allDueAmount += $amount;

                if ($notif->due_date && $notif->due_date < now()) {
                    $allOverdueCount++;
                }

                $cat = $notif->feeStructure?->feeType?->category ?? 'event_based';
                if (!in_array($cat, $allCategories)) {
                    $allCategories[] = $cat;
                }
            }

            // If no smart assignments, legacy monthly records, or notifications exist,
            // use the enrollment's own fee fields (for one_time fee type)
            if ($smartAssignments->isEmpty() && $legacyMonthlyRecords->isEmpty() && $notifications->isEmpty()) {
                $totalFee = (float) ($enrollment->total_fee ?? 0);
                $payableFee = (float) ($enrollment->payable_fee ?? 0);
                $paidAmount = (float) ($enrollment->paid_amount ?? 0);
                $dueAmount = (float) ($enrollment->due_amount ?? 0);
                $discountPercent = (float) ($enrollment->discount_percent ?? 0);
                $discountAmount = $discountPercent > 0 ? ($totalFee * $discountPercent / 100) : max(0, $totalFee - $payableFee);

                $allFeesAmount = $totalFee;
                $allPaidAmount = $paidAmount;
                $allDueAmount = $dueAmount;
                $allDiscountAmount = $discountAmount;

                if (!in_array('one_time', $allCategories)) {
                    $allCategories[] = 'one_time';
                }
            }

            // Include the one-time admission / enrollment fee so the amount the
            // student paid at enrollment is reflected in their fee summary. It is
            // separate from monthly/course fees and is normally paid up-front.
            $admissionFee = (float) ($enrollment->enrollment_fee ?? 0);
            $admissionPaid = (float) ($enrollment->enrollment_fee_paid ?? 0);
            if ($admissionFee > 0) {
                $allFeesAmount += $admissionFee;
                $allPaidAmount += $admissionPaid;
                $allDueAmount += max(0, $admissionFee - $admissionPaid);
                if (!in_array('admission', $allCategories)) {
                    $allCategories[] = 'admission';
                }
            }

            $totalFees = $allFeesAmount;
            $totalPaid = $allPaidAmount;
            $totalDue = $allDueAmount;
            $totalDiscount = $allDiscountAmount;
            $overdueCount = $allOverdueCount;
            $categories = $allCategories;

            $overallTotalFees += $totalFees;
            $overallTotalPaid += $totalPaid;
            $overallTotalDue += $totalDue;
            $overallTotalDiscount += $totalDiscount;
            $overallOverdueCount += $overdueCount;

            $status = 'clear';
            if ($totalDue > 0 && $overdueCount > 0) {
                $status = 'overdue';
            } elseif ($totalDue > 0) {
                $status = 'pending';
            }

            $enrollmentData[] = [
                'enrollment_id' => $enrollment->id,
                'course_name' => $enrollment->batch?->course?->name ?? 'N/A',
                'batch_name' => $enrollment->batch?->name ?? 'N/A',
                'total_fees' => (float) $totalFees,
                'total_paid' => (float) $totalPaid,
                'total_due' => (float) $totalDue,
                'total_discount' => (float) $totalDiscount,
                'discount_percent' => (float) ($enrollment->discount_percent ?? 0),
                'discount_reason' => $enrollment->discount_reason ?? null,
                'overdue_count' => $overdueCount,
                'status' => $status,
                'categories' => $smartAssignments->isNotEmpty() ? $categories : [],
            ];
        }

        return $this->success([
            'overall' => [
                'total_fees' => (float) $overallTotalFees,
                'total_paid' => (float) $overallTotalPaid,
                'total_due' => (float) $overallTotalDue,
                'total_discount' => (float) $overallTotalDiscount,
                'overdue_count' => $overallOverdueCount,
            ],
            'enrollments' => $enrollmentData,
        ]);
    }

    /**
     * Build ledger entries + summary contribution for the one-time admission /
     * enrollment fee, so it shows up alongside course/monthly fees in the
     * student portal. Returns ['entries' => [...], 'fees' => x, 'paid' => y, 'due' => z].
     */
    private function admissionFeeLedger(Enrollment $enrollment): array
    {
        $fee = (float) ($enrollment->enrollment_fee ?? 0);
        if ($fee <= 0) {
            return ['entries' => [], 'fees' => 0.0, 'paid' => 0.0, 'due' => 0.0];
        }

        $paid = (float) ($enrollment->enrollment_fee_paid ?? 0);
        $date = ($enrollment->enrolled_at ?? $enrollment->created_at)?->format('Y-m-d') ?? now()->format('Y-m-d');

        $entries = [[
            'date' => $date,
            'description' => 'Admission / Enrollment Fee',
            'type' => 'debit',
            'amount' => $fee,
            'discount' => 0.0,
            'net_amount' => $fee,
            'balance' => max(0, $fee - $paid),
            'status' => $paid >= $fee ? 'paid' : ($paid > 0 ? 'partial' : 'pending'),
            'fee_type_name' => 'Admission Fee',
            'fee_category' => 'admission',
        ]];

        if ($paid > 0) {
            $entries[] = [
                'date' => $date,
                'description' => 'Payment - Admission Fee' . ($enrollment->payment_method ? ' (' . $enrollment->payment_method . ')' : ''),
                'type' => 'credit',
                'amount' => $paid,
                'balance' => 0.0,
                'status' => 'confirmed',
                'fee_category' => 'admission',
            ];
        }

        return ['entries' => $entries, 'fees' => $fee, 'paid' => $paid, 'due' => max(0, $fee - $paid)];
    }

    /**
     * Get fee ledger for a specific enrollment.
     */
    public function feeLedger(string $enrollmentId): JsonResponse
    {
        $student = $this->getAuthenticatedStudent();

        if (!$student) {
            return $this->notFound('Student profile not found');
        }

        $enrollment = Enrollment::where('id', $enrollmentId)
            ->where('student_id', $student->id)
            ->with(['batch.course'])
            ->first();

        if (!$enrollment) {
            return $this->notFound('Enrollment not found');
        }

        // Check if Smart Fee system has assignments for this enrollment
        $smartAssignments = \Modules\Finance\app\Models\StudentFeeAssignment::where('enrollment_id', $enrollmentId)
            ->with(['feeStructure.feeType'])
            ->orderBy('due_date')
            ->get();

        if ($smartAssignments->isNotEmpty()) {
            // Use Smart Fee system data
            $transactions = \Modules\Finance\app\Models\PaymentTransaction::where('enrollment_id', $enrollmentId)
                ->where('status', 'confirmed')
                ->orderBy('created_at')
                ->get();

            $totalFees = $smartAssignments->sum(fn($a) => $a->original_amount + ($a->late_fee_applied ?? 0));
            $totalPaid = $smartAssignments->sum('paid_amount');
            $totalDue = $smartAssignments->sum(fn($a) => max(0, ($a->final_amount + ($a->late_fee_applied ?? 0)) - $a->paid_amount));
            $totalDiscount = $smartAssignments->sum(fn($a) => max(0, $a->original_amount - $a->final_amount));

            // Build ledger
            $ledger = [];
            $runningBalance = 0;

            foreach ($smartAssignments as $assignment) {
                $totalDueForItem = $assignment->final_amount + ($assignment->late_fee_applied ?? 0);
                $runningBalance += $totalDueForItem;

                // Resolve period label
                $periodLabel = '';
                if ($assignment->period_month) {
                    $periodLabel = \Carbon\Carbon::createFromFormat('Y-m', $assignment->period_month)?->format('F Y') ?? $assignment->period_month;
                }

                $feeType = $assignment->feeStructure?->feeType;
                $feeTypeName = $feeType?->name ?? 'Fee';
                $feeCategory = $feeType?->category ?? 'monthly';

                $ledger[] = [
                    'date' => $assignment->due_date?->format('Y-m-d') ?? $assignment->created_at->format('Y-m-d'),
                    'description' => ($feeTypeName)
                        . ($assignment->installment_number ? " (Installment {$assignment->installment_number})" : '')
                        . ($periodLabel ? " - {$periodLabel}" : ''),
                    'type' => 'debit',
                    'amount' => (float) $totalDueForItem,
                    'discount' => (float) max(0, $assignment->original_amount - $assignment->final_amount),
                    'net_amount' => (float) $assignment->final_amount,
                    'balance' => (float) $runningBalance,
                    'status' => $assignment->status,
                    'fee_type_name' => $feeTypeName,
                    'fee_category' => $feeCategory,
                ];
            }

            foreach ($transactions as $transaction) {
                $runningBalance -= $transaction->amount;

                $ledger[] = [
                    'date' => $transaction->confirmed_at?->format('Y-m-d') ?? $transaction->created_at->format('Y-m-d'),
                    'description' => 'Payment - ' . ($transaction->payment_method ?? 'N/A') . ' (' . ($transaction->transaction_no ?? 'N/A') . ')',
                    'type' => 'credit',
                    'amount' => (float) $transaction->amount,
                    'balance' => max(0, $runningBalance),
                    'status' => 'confirmed',
                ];
            }

            // Add the admission / enrollment fee line(s)
            $adm = $this->admissionFeeLedger($enrollment);
            $ledger = array_merge($ledger, $adm['entries']);
            $totalFees += $adm['fees'];
            $totalPaid += $adm['paid'];
            $totalDue += $adm['due'];

            // Sort by date
            usort($ledger, fn($a, $b) => strcmp($a['date'], $b['date']));

            return $this->success([
                'enrollment' => [
                    'id' => $enrollment->id,
                    'batch' => $enrollment->batch,
                    'discount_percent' => (float) ($enrollment->discount_percent ?? 0),
                    'discount_reason' => $enrollment->discount_reason ?? null,
                ],
                'summary' => [
                    'total_fees' => (float) $totalFees,
                    'total_paid' => (float) $totalPaid,
                    'total_due' => (float) $totalDue,
                    'total_discount' => (float) $totalDiscount,
                ],
                'records' => $smartAssignments,
                'payments' => $transactions,
                'ledger' => $ledger,
                'fee_system' => 'smart',
            ]);
        }

        // Fallback: use Monthly Fee system data
        $records = MonthlyFeeRecord::where('enrollment_id', $enrollmentId)
            ->orderBy('month', 'asc')
            ->get();

        // For one_time fee type, there are no MonthlyFeeRecord records.
        // Use the enrollment's own fee fields instead.
        if ($records->isEmpty() && $enrollment->fee_type === 'one_time') {
            $totalFee = (float) ($enrollment->total_fee ?? 0);
            $payableFee = (float) ($enrollment->payable_fee ?? 0);
            $paidAmount = (float) ($enrollment->paid_amount ?? 0);
            $dueAmount = (float) ($enrollment->due_amount ?? 0);
            $discountPercent = (float) ($enrollment->discount_percent ?? 0);
            $discountAmount = $discountPercent > 0 ? ($totalFee * $discountPercent / 100) : max(0, $totalFee - $payableFee);

            // Build a single-entry ledger for one_time fee
            $ledger = [];
            $ledger[] = [
                'date' => $enrollment->created_at->format('Y-m-d'),
                'description' => 'One-Time Fee - ' . ($enrollment->batch?->course?->name ?? 'Course'),
                'type' => 'debit',
                'amount' => (float) $totalFee,
                'discount' => (float) $discountAmount,
                'net_amount' => (float) $payableFee,
                'balance' => (float) $dueAmount,
                'status' => $enrollment->payment_status,
            ];

            // Add payment entries if any payments exist
            $payments = $enrollment->payments()->orderBy('created_at', 'desc')->get();
            foreach ($payments as $payment) {
                $ledger[] = [
                    'date' => $payment->created_at->format('Y-m-d'),
                    'description' => 'Payment - ' . ($payment->payment_method ?? 'N/A'),
                    'type' => 'credit',
                    'amount' => (float) $payment->amount,
                    'balance' => 0,
                    'status' => $payment->payment_status ?? 'confirmed',
                ];
            }

            // Add the admission / enrollment fee line(s)
            $adm = $this->admissionFeeLedger($enrollment);
            $ledger = array_merge($ledger, $adm['entries']);
            $totalFee += $adm['fees'];
            $paidAmount += $adm['paid'];
            $dueAmount += $adm['due'];

            // Sort ledger by date
            usort($ledger, function ($a, $b) {
                return strcmp($a['date'], $b['date']);
            });

            return $this->success([
                'enrollment' => [
                    'id' => $enrollment->id,
                    'batch' => $enrollment->batch,
                    'discount_percent' => (float) $discountPercent,
                    'discount_reason' => $enrollment->discount_reason ?? null,
                ],
                'summary' => [
                    'total_fees' => (float) $totalFee,
                    'total_paid' => (float) $paidAmount,
                    'total_due' => (float) $dueAmount,
                    'total_discount' => (float) $discountAmount,
                ],
                'records' => [],
                'payments' => $payments,
                'ledger' => $ledger,
            ]);
        }

        $payments = MonthlyFeePayment::whereIn('monthly_fee_record_id', $records->pluck('id'))
            ->with(['monthlyFeeRecord'])
            ->orderBy('created_at', 'desc')
            ->get();

        $totalFees = $records->sum('total_monthly_fee');
        $totalPaid = $records->sum('paid_amount');
        // Remaining due = sum of (due_amount - paid_amount) for records not fully paid
        $totalDue = $records->sum(function ($r) {
            return max(0, $r->due_amount - $r->paid_amount);
        });
        $totalDiscount = $records->sum(function ($r) {
            return max(0, $r->total_monthly_fee - $r->due_amount);
        });

        // Build complete ledger (debit/credit)
        $ledger = [];
        foreach ($records as $record) {
            $discount = max(0, $record->total_monthly_fee - $record->due_amount);
            $ledger[] = [
                'date' => $record->due_date?->format('Y-m-d') ?? $record->created_at->format('Y-m-d'),
                'description' => 'Monthly Fee - ' . $record->month,
                'type' => 'debit',
                'amount' => (float) $record->total_monthly_fee,
                'discount' => (float) $discount,
                'net_amount' => (float) $record->due_amount,
                'balance' => (float) $record->due_amount,
                'status' => $record->payment_status,
            ];
        }
        foreach ($payments as $payment) {
            $ledger[] = [
                'date' => $payment->confirmed_at?->format('Y-m-d') ?? $payment->created_at->format('Y-m-d'),
                'description' => 'Payment - ' . ($payment->payment_method ?? 'N/A') . ' (' . ($payment->transaction_id ?? 'N/A') . ')',
                'type' => 'credit',
                'amount' => (float) $payment->amount,
                'balance' => 0,
                'status' => $payment->payment_status,
            ];
        }

        // Add the admission / enrollment fee line(s)
        $adm = $this->admissionFeeLedger($enrollment);
        $ledger = array_merge($ledger, $adm['entries']);
        $totalFees += $adm['fees'];
        $totalPaid += $adm['paid'];
        $totalDue += $adm['due'];

        // Sort ledger by date
        usort($ledger, function ($a, $b) {
            return strcmp($a['date'], $b['date']);
        });

        return $this->success([
            'enrollment' => [
                'id' => $enrollment->id,
                'batch' => $enrollment->batch,
                'discount_percent' => (float) ($enrollment->discount_percent ?? 0),
                'discount_reason' => $enrollment->discount_reason ?? null,
            ],
            'summary' => [
                'total_fees' => (float) $totalFees,
                'total_paid' => (float) $totalPaid,
                'total_due' => (float) $totalDue,
                'total_discount' => (float) $totalDiscount,
            ],
            'records' => $records,
            'payments' => $payments,
            'ledger' => $ledger,
        ]);
    }

    /**
     * Get monthly fee records for an enrollment.
     */
    public function feeRecords(string $enrollmentId): JsonResponse
    {
        $student = $this->getAuthenticatedStudent();

        if (!$student) {
            return $this->notFound('Student profile not found');
        }

        $enrollment = Enrollment::where('id', $enrollmentId)
            ->where('student_id', $student->id)
            ->first();

        if (!$enrollment) {
            return $this->notFound('Enrollment not found');
        }

        // Check if Smart Fee system has assignments for this enrollment
        $smartAssignments = \Modules\Finance\app\Models\StudentFeeAssignment::where('enrollment_id', $enrollmentId)
            ->orderBy('due_date')
            ->get();

        // Also fetch legacy monthly fee records (they may exist even if smart assignments exist)
        $legacyMonthlyRecords = \Modules\Enrollment\app\Models\MonthlyFeeRecord::where('enrollment_id', $enrollmentId)
            ->orderBy('month', 'asc')
            ->get();

        // Also fetch fee notifications (exam fees, event-based fees) that don't yet
        // have a corresponding StudentFeeAssignment record.
        $notifications = \Modules\Finance\app\Models\StudentFeeNotification::where('enrollment_id', $enrollmentId)
            ->whereIn('status', ['unread', 'read'])
            ->with(['feeStructure.feeType'])
            ->get();

        if ($smartAssignments->isNotEmpty() || $legacyMonthlyRecords->isNotEmpty() || $notifications->isNotEmpty()) {
            // Map smart assignments to a format compatible with the payment page
            $smartRecords = $smartAssignments->map(function ($a) {
                $totalDue = $a->final_amount + ($a->late_fee_applied ?? 0);
                $remainingDue = max(0, $totalDue - $a->paid_amount);

                // Resolve period month label from period_month field
                $periodLabel = '';
                $dedupMonth = ''; // raw YYYY-MM for dedup
                if ($a->period_month) {
                    // Convert '2026-01' to 'January 2026'
                    $periodLabel = \Carbon\Carbon::createFromFormat('Y-m', $a->period_month)?->format('F Y') ?? $a->period_month;
                    $dedupMonth = $a->period_month; // keep raw YYYY-MM for dedup
                }

                $feeType = $a->feeStructure?->feeType;
                $feeTypeName = $feeType?->name ?? 'Fee';
                $feeCategory = $feeType?->category ?? 'monthly';
                $installmentLabel = $a->installment_number ? " (Installment {$a->installment_number})" : '';

                return [
                    'id' => $a->id,
                    'enrollment_id' => $a->enrollment_id,
                    'month' => $periodLabel
                        ? "{$feeTypeName}{$installmentLabel} - {$periodLabel}"
                        : $feeTypeName . $installmentLabel . ' - Due: ' . ($a->due_date?->format('M Y') ?? ''),
                    'total_monthly_fee' => (float) ($a->original_amount + ($a->late_fee_applied ?? 0)),
                    'due_amount' => (float) $totalDue,
                    'paid_amount' => (float) $a->paid_amount,
                    'payment_status' => $a->status === 'paid' ? 'paid' : ($a->paid_amount > 0 ? 'partial' : 'pending'),
                    'due_date' => $a->due_date?->format('Y-m-d'),
                    'is_smart_fee' => true,
                    'fee_assignment_id' => $a->id,
                    'fee_structure_id' => $a->fee_structure_id,
                    'fee_type_name' => $feeTypeName,
                    'fee_category' => $feeCategory,
                    'dedup_month' => $dedupMonth, // raw YYYY-MM for dedup matching with legacy
                ];
            })->toArray();

            // Map legacy monthly fee records to the same format
            $legacyRecords = $legacyMonthlyRecords->map(function ($r) {
                // Legacy month is already in YYYY-MM format
                $monthRaw = $r->month;
                // Format for display
                $monthLabel = $monthRaw;
                if (preg_match('/^\d{4}-\d{2}$/', $monthRaw)) {
                    $monthLabel = \Carbon\Carbon::createFromFormat('Y-m', $monthRaw)?->format('F Y') ?? $monthRaw;
                }
                return [
                    'id' => $r->id,
                    'enrollment_id' => $r->enrollment_id,
                    'month' => $monthLabel,
                    'total_monthly_fee' => (float) ($r->total_monthly_fee ?? 0),
                    'due_amount' => (float) ($r->due_amount ?? 0),
                    'paid_amount' => (float) ($r->paid_amount ?? 0),
                    'payment_status' => $r->payment_status ?? 'pending',
                    'due_date' => $r->due_date?->format('Y-m-d'),
                    'is_smart_fee' => false,
                    'fee_assignment_id' => null,
                    'fee_type_name' => 'Monthly Fee',
                    'fee_category' => 'monthly',
                    'dedup_month' => $monthRaw, // raw YYYY-MM for dedup matching with smart
                ];
            })->toArray();

            // Merge both sets of records, avoiding duplicates by fee_category + dedup_month
            // Smart records take precedence over legacy records
            $mergedRecords = [];
            $legacyKeys = [];
            $smartKeys = []; // Track which dedup keys we've already added smart records for

            foreach ($legacyRecords as $lr) {
                $key = $lr['fee_category'] . '|' . $lr['dedup_month'];
                $legacyKeys[$key] = $lr;
            }

            // Add smart records first (they take precedence)
            // For monthly category, deduplicate smart records by dedup_month (keep first one)
            foreach ($smartRecords as $sr) {
                $key = $sr['fee_category'] . '|' . $sr['dedup_month'];
                // For monthly category, skip if we already added a smart record for this month
                if ($sr['fee_category'] === 'monthly' && isset($smartKeys[$key])) {
                    continue;
                }
                $mergedRecords[] = $sr;
                $smartKeys[$key] = true;
                // Remove from legacy if smart record with same category+dedup_month exists
                unset($legacyKeys[$key]);
            }

            // Add remaining legacy records (monthly fees not yet in smart system)
            foreach ($legacyKeys as $lr) {
                $mergedRecords[] = $lr;
            }

            // Add fee notification records (exam fees, event-based fees) that don't yet
            // have a corresponding StudentFeeAssignment record.
            foreach ($notifications as $notif) {
                // Exam fees are one notification per exam — never hide because another shares fee_structure_id
                if (($notif->type ?? '') !== 'exam_fee') {
                    $existingAssignment = \Modules\Finance\app\Models\StudentFeeAssignment::where('enrollment_id', $enrollmentId)
                        ->where('fee_structure_id', $notif->fee_structure_id)
                        ->whereIn('status', ['pending', 'partial'])
                        ->exists();

                    if ($existingAssignment) {
                        continue;
                    }
                }

                $feeType = $notif->feeStructure?->feeType;
                $feeTypeName = $feeType?->name ?? ($notif->title ?? 'Exam Fee');
                $amount = (float) ($notif->amount ?? 0);

                $mergedRecords[] = [
                    'id' => 'notif_' . $notif->id,
                    'enrollment_id' => $notif->enrollment_id,
                    'month' => $notif->title ?? $feeTypeName,
                    'total_monthly_fee' => $amount,
                    'due_amount' => $amount,
                    'paid_amount' => 0,
                    'payment_status' => 'pending',
                    'status' => $notif->status,
                    'due_date' => $notif->due_date?->format('Y-m-d'),
                    'is_smart_fee' => false,
                    'fee_assignment_id' => null,
                    'fee_type_name' => $notif->title ?? $feeTypeName,
                    'title' => $notif->title ?? $feeTypeName,
                    'fee_category' => $feeType?->category ?? 'event_based',
                    'type' => $notif->type ?? 'exam_fee',
                    'dedup_month' => '',
                    'is_notification' => true,
                    'notification_id' => $notif->id,
                    'fee_structure_id' => $notif->fee_structure_id,
                    'meta' => $notif->meta ?? [],
                    'exam_id' => $notif->meta['exam_id'] ?? null,
                ];
            }

            // Sort merged records by due_date
            usort($mergedRecords, function ($a, $b) {
                $dateA = $a['due_date'] ?? '9999-12-31';
                $dateB = $b['due_date'] ?? '9999-12-31';
                return strcmp($dateA, $dateB);
            });

            // Calculate totals from merged records
            $totalFees = array_sum(array_column($mergedRecords, 'total_monthly_fee'));
            $totalPaid = array_sum(array_column($mergedRecords, 'paid_amount'));
            $totalDue = array_sum(array_map(function ($r) {
                return max(0, ($r['due_amount'] ?? 0) - ($r['paid_amount'] ?? 0));
            }, $mergedRecords));
            $totalDiscount = array_sum(array_map(function ($r) {
                return max(0, ($r['total_monthly_fee'] ?? 0) - ($r['due_amount'] ?? 0));
            }, $mergedRecords));
            $paidCount = count(array_filter($mergedRecords, fn($r) => $r['payment_status'] === 'paid'));
            $totalCount = count($mergedRecords);

            // Available online gateways for auto-confirm
            $gateways = [
                ['code' => 'bkash', 'name' => 'bKash', 'icon' => '💳'],
                ['code' => 'nagad', 'name' => 'Nagad', 'icon' => '💳'],
                ['code' => 'rocket', 'name' => 'Rocket', 'icon' => '🚀'],
                ['code' => 'card', 'name' => 'Credit/Debit Card', 'icon' => '💳'],
                ['code' => 'bank_transfer', 'name' => 'Bank Transfer', 'icon' => '🏦'],
            ];

            // Build enriched summary matching admin getSummary format
            $enrichedSummary = [
                'total_months' => $totalCount,
                'paid_months' => $paidCount,
                'total_due' => (float) $totalDue,
                'total_discount' => (float) $totalDiscount,
                'total_fee' => (float) $totalFees,
            ];

            // Find last paid month info
            $lastPaid = null;
            $nextDue = null;
            $paidSorted = array_filter($mergedRecords, fn($r) => $r['payment_status'] === 'paid');
            if (!empty($paidSorted)) {
                usort($paidSorted, fn($a, $b) => strcmp($b['dedup_month'] ?? $b['due_date'] ?? '', $a['dedup_month'] ?? $a['due_date'] ?? ''));
                $lastPaid = $paidSorted[0];
            }
            $dueSorted = array_filter($mergedRecords, fn($r) => $r['payment_status'] !== 'paid');
            if (!empty($dueSorted)) {
                usort($dueSorted, fn($a, $b) => strcmp($a['dedup_month'] ?? $a['due_date'] ?? '', $b['dedup_month'] ?? $b['due_date'] ?? ''));
                $nextDue = $dueSorted[0];
            }

            $enrichedSummary['last_paid_month'] = $lastPaid['dedup_month'] ?? null;
            $enrichedSummary['last_paid_month_name'] = null;
            if ($lastPaid && !empty($lastPaid['dedup_month'])) {
                try { $enrichedSummary['last_paid_month_name'] = \Carbon\Carbon::createFromFormat('Y-m', $lastPaid['dedup_month'])->format('F Y'); } catch (\Exception $e) {}
            }
            $enrichedSummary['last_paid_amount'] = (float) ($lastPaid['paid_amount'] ?? 0);
            $enrichedSummary['next_month_name'] = null;
            if ($nextDue && !empty($nextDue['dedup_month'])) {
                try { $enrichedSummary['next_month_name'] = \Carbon\Carbon::createFromFormat('Y-m', $nextDue['dedup_month'])->format('F Y'); } catch (\Exception $e) {}
            }
            $enrichedSummary['next_month_due'] = (float) ($nextDue ? max(0, ($nextDue['due_amount'] ?? 0) - ($nextDue['paid_amount'] ?? 0)) : 0);
            $enrichedSummary['next_month_status'] = $nextDue['payment_status'] ?? 'pending';
            $enrichedSummary['next_unpaid_month'] = $nextDue;

            return $this->success([
                'records' => $mergedRecords,
                'summary' => $enrichedSummary,
                'enrollment' => [
                    'id' => $enrollment->id,
                    'student_id' => $enrollment->student_id,
                    'discount_percent' => (float) ($enrollment->discount_percent ?? 0),
                    'discount_reason' => $enrollment->discount_reason ?? null,
                ],
                'gateways' => $gateways,
                'fee_system' => 'smart',
            ]);
        }

        // Fallback: use Monthly Fee system data
        $records = MonthlyFeeRecord::where('enrollment_id', $enrollmentId)
            ->orderBy('month', 'asc')
            ->get();

        // For one_time fee type, there are no MonthlyFeeRecord records.
        // Use the enrollment's own fee fields instead.
        if ($records->isEmpty() && $enrollment->fee_type === 'one_time') {
            $totalFee = (float) ($enrollment->total_fee ?? 0);
            $payableFee = (float) ($enrollment->payable_fee ?? 0);
            $paidAmount = (float) ($enrollment->paid_amount ?? 0);
            $dueAmount = (float) ($enrollment->due_amount ?? 0);
            $discountPercent = (float) ($enrollment->discount_percent ?? 0);
            $discountAmount = $discountPercent > 0 ? ($totalFee * $discountPercent / 100) : max(0, $totalFee - $payableFee);

            $gateways = [
                ['code' => 'bkash', 'name' => 'bKash', 'icon' => '💳'],
                ['code' => 'nagad', 'name' => 'Nagad', 'icon' => '💳'],
                ['code' => 'rocket', 'name' => 'Rocket', 'icon' => '🚀'],
                ['code' => 'card', 'name' => 'Credit/Debit Card', 'icon' => '💳'],
                ['code' => 'bank_transfer', 'name' => 'Bank Transfer', 'icon' => '🏦'],
            ];

            $courseName = $enrollment->batch?->course?->name ?? 'Course';
            $mappedRecords = [[
                'id' => 'enrollment_' . $enrollment->id,
                'enrollment_id' => $enrollment->id,
                'month' => 'Course Fee - ' . $courseName,
                'title' => 'Course Fee - ' . $courseName,
                'total_monthly_fee' => $totalFee,
                'due_amount' => $payableFee,
                'paid_amount' => $paidAmount,
                'payment_status' => $enrollment->payment_status,
                'due_date' => $enrollment->created_at->format('Y-m-d'),
                'fine_amount' => 0,
                'is_smart_fee' => false,
                'fee_type_name' => 'Course Fee',
                'fee_category' => 'one_time',
            ]];

            return $this->success([
                'records' => $mappedRecords,
                'summary' => [
                    'total_months' => 1,
                    'paid_months' => $enrollment->payment_status === 'paid' ? 1 : 0,
                    'total_due' => (float) $dueAmount,
                    'total_discount' => (float) $discountAmount,
                ],
                'enrollment' => [
                    'id' => $enrollment->id,
                    'student_id' => $enrollment->student_id,
                    'discount_percent' => (float) $discountPercent,
                    'discount_reason' => $enrollment->discount_reason ?? null,
                ],
                'gateways' => $gateways,
                'fee_system' => 'monthly',
            ]);
        }

        $totalMonths = $records->count();
        $paidMonths = $records->where('payment_status', 'paid')->count();
        // Remaining due = sum of (due_amount - paid_amount) for records not fully paid
        $totalDue = $records->sum(function ($r) {
            return max(0, $r->due_amount - $r->paid_amount);
        });
        $totalDiscount = $records->sum(function ($r) {
            return max(0, $r->total_monthly_fee - $r->due_amount);
        });

        // Available online gateways for auto-confirm
        $gateways = [
            ['code' => 'bkash', 'name' => 'bKash', 'icon' => '💳'],
            ['code' => 'nagad', 'name' => 'Nagad', 'icon' => '💳'],
            ['code' => 'rocket', 'name' => 'Rocket', 'icon' => '🚀'],
            ['code' => 'card', 'name' => 'Credit/Debit Card', 'icon' => '💳'],
            ['code' => 'bank_transfer', 'name' => 'Bank Transfer', 'icon' => '🏦'],
        ];

        // Map monthly records to include proper month name formatting
        $mappedRecords = $records->map(function ($r) {
            $monthLabel = $r->month;
            // Convert '2026-01' format to 'January 2026'
            if (preg_match('/^\d{4}-\d{2}$/', $r->month)) {
                $monthLabel = \Carbon\Carbon::createFromFormat('Y-m', $r->month)?->format('F Y') ?? $r->month;
            }
            return [
                'id' => $r->id,
                'enrollment_id' => $r->enrollment_id,
                'month' => $monthLabel,
                'total_monthly_fee' => (float) $r->total_monthly_fee,
                'due_amount' => (float) $r->due_amount,
                'paid_amount' => (float) $r->paid_amount,
                'payment_status' => $r->payment_status,
                'due_date' => $r->due_date?->format('Y-m-d'),
                'fine_amount' => (float) ($r->fine_amount ?? 0),
                'is_smart_fee' => false,
                'dedup_month' => preg_match('/^\d{4}-\d{2}$/', $r->month) ? $r->month : '',
            ];
        })->toArray();

        return $this->success([
            'records' => $mappedRecords,
            'summary' => [
                'total_months' => $totalMonths,
                'paid_months' => $paidMonths,
                'total_due' => (float) $totalDue,
                'total_discount' => (float) $totalDiscount,
            ],
            'enrollment' => [
                'id' => $enrollment->id,
                'student_id' => $enrollment->student_id,
                'discount_percent' => (float) ($enrollment->discount_percent ?? 0),
                'discount_reason' => $enrollment->discount_reason ?? null,
            ],
            'gateways' => $gateways,
            'fee_system' => 'monthly',
        ]);
    }

    /**
     * Get upcoming exams for the student.
     */
    public function exams(): JsonResponse
    {
        $student = $this->getAuthenticatedStudent();

        if (!$student) {
            return $this->notFound('Student profile not found');
        }

        $batchIds = $this->examEligibility->activeEnrollmentBatchIds($student->id)->all();

        if ($batchIds === []) {
            return $this->success([]);
        }

        $candidateExamIds = ExamRoutine::published()
            ->offlineChannel()
            ->whereIn('batch_id', $batchIds)
            ->whereHas('exam', fn ($q) => $q
                ->where('status', 'published')
                ->where('is_practice', false))
            ->distinct()
            ->pluck('exam_id');

        $exams = Exam::whereIn('id', $candidateExamIds)
            ->with(['examType', 'class', 'section', 'batch', 'course'])
            ->orderBy('start_date')
            ->get()
            ->values()
            ->map(function (Exam $exam) use ($student, $batchIds) {
                $data = $exam->toArray();

                $studentRoutines = ExamRoutine::published()
                    ->offlineChannel()
                    ->where('exam_id', $exam->id)
                    ->whereIn('batch_id', $batchIds)
                    ->with(['batch.course.class', 'subject', 'class', 'exam.examType', 'exam.class', 'exam.course'])
                    ->orderBy('exam_date')
                    ->orderBy('start_time')
                    ->get();

                $primaryRoutine = $studentRoutines->first();
                $displayMeta = $primaryRoutine
                    ? $this->examEligibility->resolveRoutineDisplayMeta($primaryRoutine, $student->id)
                    : [
                        'batch_name' => $exam->batch?->name,
                        'course_name' => $exam->course?->name,
                        'class_name' => $exam->class?->name ?? $exam->course?->class?->name,
                        'exam_type_name' => $exam->examType?->name,
                    ];

                $data['exam_type_name'] = $displayMeta['exam_type_name'];
                $data['batch_name'] = $displayMeta['batch_name'];
                $data['course_name'] = $displayMeta['course_name'];
                $data['class_name'] = $displayMeta['class_name'];
                if (!empty($displayMeta['class_name'])) {
                    $data['class'] = ['name' => $displayMeta['class_name']];
                }
                $data['routine_count'] = $studentRoutines->count();
                $data['subject_count'] = $studentRoutines->pluck('subject_id')->filter()->unique()->count();
                $data['first_routine_date'] = $primaryRoutine?->exam_date?->format('Y-m-d');
                $data['last_routine_date'] = $studentRoutines->last()?->exam_date?->format('Y-m-d');
                $data['exam_eligibility'] = $this->examEligibility->getStudentStatus(
                    $exam->id,
                    $student->id,
                    false
                );

                return $data;
            })
            ->values();

        return $this->success($exams);
    }

    /**
     * Get exam routines/schedule for the student.
     */
    public function examRoutines(): JsonResponse
    {
        $student = $this->getAuthenticatedStudent();

        if (!$student) {
            return $this->notFound('Student profile not found');
        }

        $batchIds = $this->examEligibility->activeEnrollmentBatchIds($student->id)->all();

        if ($batchIds === []) {
            return $this->success([]);
        }

        $routines = ExamRoutine::published()
            ->offlineChannel()
            ->whereIn('batch_id', $batchIds)
            ->whereHas('exam', fn ($q) => $q
                ->where('status', 'published')
                ->where('is_practice', false))
            ->with(['exam.examType', 'exam.class', 'exam.batch', 'subject', 'room'])
            ->orderBy('exam_date')
            ->orderBy('start_time')
            ->get()
            ->values();

        return $this->success($routines);
    }

    /**
     * Get exam results for the student.
     *
     * @deprecated Use Modules\Exam StudentExamController::results() instead.
     *             This legacy endpoint lacks channel split, rank, and leaderboard support.
     */
    public function examResults(): JsonResponse
    {
        $student = $this->getAuthenticatedStudent();

        if (!$student) {
            return $this->notFound('Student profile not found');
        }

        $results = ExamResult::where('student_id', $student->id)
            ->where('status', 'published')
            ->with(['exam.examType', 'exam.class', 'subject'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Group results by exam
        $grouped = $results->groupBy('exam_id')->map(function ($examResults, $examId) {
            $exam = $examResults->first()->exam;
            $totalMarks = $examResults->sum('total_marks');
            $obtainedMarks = $examResults->sum('marks_obtained');
            $percentage = $totalMarks > 0 ? round(($obtainedMarks / $totalMarks) * 100, 2) : 0;

            return [
                'exam' => $exam,
                'subjects' => $examResults,
                'total_marks' => (float) $totalMarks,
                'obtained_marks' => (float) $obtainedMarks,
                'percentage' => $percentage,
                'grade' => $this->gradingService->calculateGradeLetter($percentage),
            ];
        })->values();

        return $this->success($grouped)
            ->header('Deprecation', 'true')
            ->header('Link', '</api/v1/student/exam-results>; rel="successor-version"');
    }

    /**
     * Get class routine for the student.
     */
    public function classRoutines(): JsonResponse
    {
        $student = $this->getAuthenticatedStudent();

        if (!$student) {
            return $this->notFound('Student profile not found');
        }

        $batchIds = Enrollment::where('student_id', $student->id)
            ->where('status', 'active')
            ->pluck('batch_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($batchIds === []) {
            $days = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
            $grouped = collect($days)->mapWithKeys(fn ($day) => [$day => collect()]);

            return $this->success($grouped);
        }

        $routines = ClassRoutine::whereIn('batch_id', $batchIds)
            ->with(['class', 'section', 'subject', 'teacher', 'room', 'period'])
            ->orderBy('day_of_week')
            ->orderBy('period_id')
            ->get();

        // Group by day of week
        $days = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $grouped = collect($days)->mapWithKeys(function ($day) use ($routines) {
            $dayIndex = array_search($day, ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']);
            return [$day => $routines->where('day_of_week', $dayIndex + 1)->values()];
        });

        return $this->success($grouped);
    }

    /**
     * Get published notices.
     */
    public function notices(): JsonResponse
    {
        $notices = $this->noticeBoardService->getPublishedForUser(auth()->user());

        return $this->success(
            $this->noticeBoardService->enrichCollectionForPortal($notices)
        );
    }

    /**
     * Apply for leave.
     */
    public function applyLeave(Request $request): JsonResponse
    {
        $student = $this->getAuthenticatedStudent();

        if (!$student) {
            return $this->notFound('Student profile not found');
        }

        $validated = $request->validate([
            'leave_type' => 'required|string|in:sick,personal,emergency,other',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:1000',
        ]);

        $startDate = \Carbon\Carbon::parse($validated['start_date']);
        $endDate = \Carbon\Carbon::parse($validated['end_date']);
        $totalDays = $startDate->diffInDays($endDate) + 1;

        // Check if there's already a pending leave for this period
        $existingLeave = StudentLeave::where('student_id', $student->id)
            ->where('status', 'pending')
            ->where(function ($q) use ($validated) {
                $q->whereBetween('start_date', [$validated['start_date'], $validated['end_date']])
                  ->orWhereBetween('end_date', [$validated['start_date'], $validated['end_date']]);
            })
            ->first();

        if ($existingLeave) {
            return $this->error('You already have a pending leave request for this period', 422);
        }

        $leave = StudentLeave::create([
            'student_id' => $student->id,
            'leave_type' => $validated['leave_type'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'total_days' => $totalDays,
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);

        return $this->created($leave, 'Leave application submitted successfully');
    }

    /**
     * Get student's leave applications.
     */
    public function leaveApplications(): JsonResponse
    {
        $student = $this->getAuthenticatedStudent();

        if (!$student) {
            return $this->notFound('Student profile not found');
        }

        $leaves = StudentLeave::where('student_id', $student->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success($leaves);
    }

    /**
     * Admin: Get all student leave applications (with filters).
     */
    public function adminLeaveList(Request $request): JsonResponse
    {
        $query = StudentLeave::with(['student.currentClass', 'student.currentSection', 'approver']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by leave type
        if ($request->filled('leave_type')) {
            $query->where('leave_type', $request->leave_type);
        }

        // Search by student name/ID
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('student_id', 'like', "%{$search}%");
            });
        }

        $leaves = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return $this->paginatedResponse($leaves);
    }

    /**
     * Admin: Approve a student leave application.
     */
    public function adminApproveLeave(string $id): JsonResponse
    {
        $leave = StudentLeave::find($id);

        if (!$leave) {
            return $this->notFound('Leave application not found');
        }

        if ($leave->status !== 'pending') {
            return $this->error('Leave application is already ' . $leave->status, 422);
        }

        $leave->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return $this->success($leave->load('student'), 'Leave approved successfully');
    }

    /**
     * Admin: Reject a student leave application.
     */
    public function adminRejectLeave(Request $request, string $id): JsonResponse
    {
        $leave = StudentLeave::find($id);

        if (!$leave) {
            return $this->notFound('Leave application not found');
        }

        if ($leave->status !== 'pending') {
            return $this->error('Leave application is already ' . $leave->status, 422);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $leave->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return $this->success($leave->load('student'), 'Leave rejected successfully');
    }

    /**
     * Get attendance records for the student.
     */
    public function attendance(): JsonResponse
    {
        $student = $this->getAuthenticatedStudent();

        if (!$student) {
            return $this->notFound('Student profile not found');
        }

        return $this->success(
            $this->portalAttendance->getPortalData('student', $student->id)
        );
    }

    /**
     * Get enrollment details with subjects and teachers for the student.
     */
    public function enrollmentDetails(string $enrollmentId): JsonResponse
    {
        $student = $this->getAuthenticatedStudent();

        if (!$student) {
            return $this->notFound('Student profile not found');
        }

        $enrollment = Enrollment::where('id', $enrollmentId)
            ->where('student_id', $student->id)
            ->with([
                'batch.course',
                'batch.academicSession',
                'batch.course.subjects',
                'enrolledClass',
                'enrolledSection',
                'enrolledGroup',
                'subjects',
            ])
            ->first();

        if (!$enrollment) {
            return $this->notFound('Enrollment not found');
        }

        // Get teachers for this enrollment's subjects
        $teachers = [];
        if (class_exists('\Modules\Academic\app\Models\SubjectTeacher')) {
            $subjectTeacherClass = '\Modules\Academic\app\Models\SubjectTeacher';
            $subjectIds = $enrollment->subjects->pluck('id')->toArray();
            if (!empty($subjectIds)) {
                $teachers = $subjectTeacherClass::whereIn('subject_id', $subjectIds)
                    ->where('batch_id', $enrollment->batch_id)
                    ->with(['teacher', 'subject'])
                    ->get();
            }
        }

        return $this->success([
            'enrollment' => $enrollment,
            'teachers' => $teachers,
        ]);
    }

    /**
     * Get study materials scoped to the authenticated student.
     */
    public function studyMaterials(): JsonResponse
    {
        $student = $this->getAuthenticatedStudent();

        if (!$student) {
            return $this->notFound('Student profile not found');
        }

        $materials = $this->studyMaterialService->listForStudent($student);

        return $this->success(
            $this->studyMaterialService->enrichCollection($materials)
        );
    }

    /**
     * Get downloadable resources available to the authenticated user.
     */
    public function downloads(): JsonResponse
    {
        $resources = $this->downloadResourceService->listForUser(auth()->user());

        return $this->success(
            $this->downloadResourceService->enrichCollection($resources)
        );
    }

}
