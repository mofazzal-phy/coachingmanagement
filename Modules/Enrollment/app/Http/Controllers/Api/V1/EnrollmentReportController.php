<?php

namespace Modules\Enrollment\app\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Enrollment\app\Models\Enrollment;
use Modules\Enrollment\app\Models\Batch;
use Modules\Enrollment\app\Models\Course;
use Modules\Enrollment\app\Services\EnrollmentService;
use Modules\Student\app\Models\Student;
use Modules\Teacher\app\Models\Teacher;

class EnrollmentReportController extends BaseApiController
{
    protected $enrollmentService;

    public function __construct(EnrollmentService $enrollmentService)
    {
        $this->enrollmentService = $enrollmentService;
    }

    public function summary()
    {
        $stats = $this->enrollmentService->getStats();
        return $this->success($stats);
    }

    public function batchWise()
    {
        $batches = Batch::withCount(['enrollments' => function ($q) {
            $q->where('status', 'active');
        }])->with(['course:id,name,category'])->get()
            ->map(function ($batch) {
                return [
                    'id' => $batch->id,
                    'name' => $batch->name,
                    'course' => $batch->course?->name,
                    'category' => $batch->course?->category,
                    'mode' => $batch->mode,
                    'capacity' => $batch->capacity,
                    'enrolled' => $batch->enrollments_count,
                    'available' => $batch->availableSeats(),
                    'status' => $batch->status,
                ];
            });

        return $this->success($batches);
    }

    public function courseWise()
    {
        $courses = Course::withCount(['enrollments' => function ($q) {
            $q->where('status', 'active');
        }])->with(['batches'])->get()
            ->map(function ($course) {
                $totalCapacity = $course->batches->sum('capacity');
                $totalEnrolled = $course->batches->sum('enrolled_count');
                return [
                    'id' => $course->id,
                    'name' => $course->name,
                    'category' => $course->category,
                    'total_batches' => $course->batches->count(),
                    'total_capacity' => $totalCapacity,
                    'total_enrolled' => $totalEnrolled,
                    'occupancy_rate' => $totalCapacity > 0 ? round(($totalEnrolled / $totalCapacity) * 100, 2) : 0,
                ];
            });

        return $this->success($courses);
    }

    public function modeWise()
    {
        $stats = Enrollment::selectRaw("
            mode,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'dropped' THEN 1 ELSE 0 END) as dropped,
            SUM(paid_amount) as revenue
        ")->groupBy('mode')->get();

        return $this->success($stats);
    }

    public function dailyEnrollment(Request $request)
    {
        $days = $request->get('days', 30);

        $stats = Enrollment::selectRaw("
            DATE(created_at) as date,
            COUNT(*) as count,
            SUM(paid_amount) as revenue
        ")->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $this->success($stats);
    }

    public function revenueProjection()
    {
        $activeEnrollments = Enrollment::where('status', 'active')->count();
        $averageFee = Enrollment::where('status', 'active')->avg('payable_fee');
        $monthlyRevenue = $activeEnrollments * $averageFee;

        return $this->success([
            'active_enrollments' => $activeEnrollments,
            'average_monthly_fee' => round($averageFee, 2),
            'projected_monthly_revenue' => round($monthlyRevenue, 2),
            'projected_yearly_revenue' => round($monthlyRevenue * 12, 2),
        ]);
    }

    /**
     * Dashboard overview: key numbers at a glance.
     */
    public function overview()
    {
        $enrollments = Enrollment::query();
        return $this->success([
            'total_students' => Student::count(),
            'active_enrollments' => Enrollment::where('status', 'active')->count(),
            'total_revenue' => (int) Enrollment::sum('paid_amount'),
            'total_batches' => Batch::count(),
            'open_batches' => Batch::where('status', 'open')->count(),
            'avg_occupancy' => Batch::sum('capacity') > 0
                ? round(Enrollment::where('status', 'active')->count() / max(Batch::sum('capacity'),1) * 100)
                : 0,
        ]);
    }

    /**
     * Revenue trend by month with optional date filter.
     */
    public function revenueTrend(Request $request)
    {
        $trend = [];
        $months = $request->months ?? 6;
        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $rev = (int) Enrollment::whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)->sum('paid_amount');
            $cnt = Enrollment::whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)->count();
            $trend[] = ['month' => $month->format('M'), 'revenue' => $rev, 'enrollments' => $cnt];
        }
        return $this->success($trend);
    }

    /**
     * Batch occupancy report.
     */
    public function occupancy(Request $request)
    {
        $batches = Batch::with('course:id,name')
            ->orderBy('enrolled_count', 'desc')
            ->get()
            ->map(fn($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'course' => $b->course?->name,
                'capacity' => $b->capacity,
                'enrolled' => $b->enrolled_count,
                'waiting' => $b->waiting_list_count,
                'occupancy' => $b->capacity > 0 ? round($b->enrolled_count / $b->capacity * 100) : 0,
                'status' => $b->status,
            ]);

        return $this->success($batches);
    }

    /**
     * Teacher performance report.
     */
    public function teacherPerformance(Request $request)
    {
        $query = \Modules\Teacher\app\Models\Teacher::withCount(['batches'])
            ->withSum(['batches as total_enrolled' => fn($q) => $q->selectRaw('sum(enrolled_count)')], 'enrolled_count');

        $teachers = $query->orderByDesc('total_enrolled')->get()->map(function ($t) {
            $enrollments = Enrollment::whereIn('batch_id', $t->batches->pluck('id'));
            if (request('from_date')) $enrollments->whereDate('created_at', '>=', request('from_date'));
            if (request('to_date')) $enrollments->whereDate('created_at', '<=', request('to_date'));

            return [
                'id' => $t->id,
                'name' => $t->first_name . ' ' . $t->last_name,
                'batches' => $t->batches_count,
                'total_enrolled' => (int) $t->total_enrolled,
                'revenue' => (int) $enrollments->sum('paid_amount'),
                'active_students' => $enrollments->where('status', 'active')->count(),
            ];
        });

        return $this->success($teachers);
    }

    /**
     * Batch performance report with date filter.
     */
    public function batchPerformance(Request $request)
    {
        $batches = Batch::with('course:id,name')->get()->map(function ($b) use ($request) {
            $enrollments = Enrollment::where('batch_id', $b->id);
            if ($request->from_date) $enrollments->whereDate('created_at', '>=', $request->from_date);
            if ($request->to_date) $enrollments->whereDate('created_at', '<=', $request->to_date);

            return [
                'id' => $b->id,
                'name' => $b->name,
                'course' => $b->course?->name,
                'capacity' => $b->capacity,
                'enrolled' => $b->enrolled_count,
                'active' => $enrollments->where('status', 'active')->count(),
                'dropped' => $enrollments->where('status', 'dropped')->count(),
                'revenue' => (int) $enrollments->sum('paid_amount'),
                'fill_rate' => $b->capacity > 0 ? round($b->enrolled_count / $b->capacity * 100) : 0,
                'status' => $b->status,
            ];
        })->sortByDesc('revenue')->values();

        return $this->success($batches);
    }

    /**
     * Export PDF (HTML with print styles).
     */
    public function exportPdf(Request $request)
    {
        $type = $request->type ?? 'revenue';
        $data = $this->getReportData($type, $request);
        $title = ucfirst(str_replace('-',' ',$type)).' Report';
        $date = now()->format('d M Y');

        $rows = '';
        foreach ($data as $row) {
            $cells = '';
            foreach ($row as $val) {
                $cells .= '<td>'.e(is_numeric($val)?number_format((int)$val):$val).'</td>';
            }
            $rows .= "<tr>{$cells}</tr>";
        }

        $headers = array_keys($data[0] ?? []);
        $headerCells = '';
        foreach ($headers as $h) $headerCells .= '<th>'.e(ucwords(str_replace('_',' ',$h))).'</th>';

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>'.$title.'</title>
        <style>
          body{font-family:Arial,sans-serif;max-width:900px;margin:20px auto;color:#222}
          h1{text-align:center;font-size:18px;border-bottom:2px solid #333;padding-bottom:10px}
          .sub{text-align:center;color:#666;font-size:12px;margin-bottom:15px}
          table{width:100%;border-collapse:collapse;font-size:11px}
          th{background:#2c3e50;color:#fff;padding:8px 10px;text-align:left;text-transform:uppercase;font-size:10px}
          td{padding:6px 10px;border-bottom:1px solid #eee}
          tr:nth-child(even){background:#f9fafb}
          .footer{text-align:center;margin-top:20px;font-size:10px;color:#999;border-top:1px solid #eee;padding-top:10px}
          @media print{body{margin:0;padding:10px}}
        </style></head><body>
        <h1>'.$title.'</h1>
        <p class="sub">Generated: '.$date.'</p>
        <table><thead><tr>'.$headerCells.'</tr></thead><tbody>'.$rows.'</tbody></table>
        <p class="footer">Coaching Management System · Computer Generated Report</p>
        </body></html>';

        return response($html, 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => 'attachment; filename="'.str_slug($title).'-'.$date.'.html"',
        ]);
    }

    /**
     * Export Excel (CSV with Excel MIME).
     */
    public function exportExcel(Request $request)
    {
        $type = $request->type ?? 'revenue';
        $data = $this->getReportData($type, $request);

        if (empty($data)) return response('No data', 400);

        $headers = array_keys($data[0]);
        $csv = implode(',', array_map(fn($h)=>'"'.ucwords(str_replace('_',' ',$h)).'"',$headers))."\n";
        foreach ($data as $row) {
            $csv .= implode(',', array_map(fn($v)=>'"'.str_replace('"','""',$v).'"',$row))."\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => 'attachment; filename="'.str_slug(ucfirst($type)).'-report-'.now()->format('Y-m-d').'.csv"',
        ]);
    }

    /**
     * Get report data by type (shared helper).
     */
    private function getReportData(string $type, Request $request): array
    {
        switch ($type) {
            case 'revenue':
            case 'trend':
                $trend = $this->revenueTrendData($request);
                return array_map(fn($t) => [
                    'month' => $t['month'],
                    'enrollments' => $t['enrollments'],
                    'revenue' => $t['revenue'],
                ], $trend);

            case 'occupancy':
                $occ = $this->occupancyData();
                return array_map(fn($o) => [
                    'batch' => $o['name'],
                    'course' => $o['course'] ?? '',
                    'capacity' => $o['capacity'],
                    'enrolled' => $o['enrolled'],
                    'waiting' => $o['waiting'] ?? 0,
                    'occupancy_pct' => $o['occupancy'].'%',
                    'status' => $o['status'],
                ], $occ);

            case 'teacher':
                $t = $this->teacherPerformanceData($request);
                return array_map(fn($r) => [
                    'teacher' => $r['name'],
                    'batches' => $r['batches'],
                    'total_enrolled' => $r['total_enrolled'],
                    'active' => $r['active_students'],
                    'revenue' => $r['revenue'],
                ], $t);

            case 'batch':
                $b = $this->batchPerformanceData($request);
                return array_map(fn($r) => [
                    'batch' => $r['name'],
                    'course' => $r['course'] ?? '',
                    'fill_rate' => $r['fill_rate'].'%',
                    'enrolled' => $r['enrolled'],
                    'active' => $r['active'],
                    'dropped' => $r['dropped'],
                    'revenue' => $r['revenue'],
                ], $b);

            case 'course':
                $c = $this->coursePerformanceData($request);
                return array_map(fn($r) => [
                    'course' => $r['name'],
                    'code' => $r['code'],
                    'category' => $r['category'],
                    'batches' => $r['batches'],
                    'total_enrolled' => $r['total_enrolled'],
                    'active' => $r['active'],
                    'revenue' => $r['revenue'],
                ], $c);
        }
        return [];
    }

    private function revenueTrendData(Request $r): array {
        $trend = []; $months = $r->months ?? 6;
        for ($i = $months-1; $i >= 0; $i--) {
            $m = now()->subMonths($i);
            $trend[] = ['month'=>$m->format('M'),'enrollments'=>Enrollment::whereYear('created_at',$m->year)->whereMonth('created_at',$m->month)->count(),'revenue'=>(int)Enrollment::whereYear('created_at',$m->year)->whereMonth('created_at',$m->month)->sum('paid_amount')];
        }
        return $trend;
    }
    private function occupancyData(): array {
        return Batch::with('course:id,name')->orderBy('enrolled_count','desc')->get()->map(fn($b)=>['id'=>$b->id,'name'=>$b->name,'course'=>$b->course?->name,'capacity'=>$b->capacity,'enrolled'=>$b->enrolled_count,'waiting'=>$b->waiting_list_count,'occupancy'=>$b->capacity>0?round($b->enrolled_count/$b->capacity*100):0,'status'=>$b->status])->toArray();
    }
    private function teacherPerformanceData(Request $r): array {
        return \Modules\Teacher\app\Models\Teacher::withCount(['batches'])->get()->map(function($t)use($r){
            $e = Enrollment::whereIn('batch_id',$t->batches->pluck('id')); if($r->from_date)$e->whereDate('created_at','>=',$r->from_date); if($r->to_date)$e->whereDate('created_at','<=',$r->to_date);
            return ['id'=>$t->id,'name'=>$t->first_name.' '.$t->last_name,'batches'=>$t->batches_count,'total_enrolled'=>(int)$t->batches->sum('enrolled_count'),'active_students'=>$e->where('status','active')->count(),'revenue'=>(int)$e->sum('paid_amount')];
        })->sortByDesc('revenue')->values()->toArray();
    }
    private function batchPerformanceData(Request $r): array {
        return Batch::with('course:id,name')->get()->map(function($b)use($r){
            $e = Enrollment::where('batch_id',$b->id); if($r->from_date)$e->whereDate('created_at','>=',$r->from_date); if($r->to_date)$e->whereDate('created_at','<=',$r->to_date);
            return ['id'=>$b->id,'name'=>$b->name,'course'=>$b->course?->name,'capacity'=>$b->capacity,'enrolled'=>$b->enrolled_count,'active'=>$e->where('status','active')->count(),'dropped'=>$e->where('status','dropped')->count(),'revenue'=>(int)$e->sum('paid_amount'),'fill_rate'=>$b->capacity>0?round($b->enrolled_count/$b->capacity*100):0,'status'=>$b->status];
        })->sortByDesc('revenue')->values()->toArray();
    }
    private function coursePerformanceData(Request $r): array {
        return Course::withCount(['batches'])->get()->map(function($c)use($r){
            $e = Enrollment::whereIn('batch_id',$c->batches->pluck('id')); if($r->from_date)$e->whereDate('created_at','>=',$r->from_date); if($r->to_date)$e->whereDate('created_at','<=',$r->to_date);
            return ['id'=>$c->id,'name'=>$c->name,'code'=>$c->code,'category'=>$c->category,'batches'=>$c->batches_count,'total_enrolled'=>$e->count(),'active'=>$e->where('status','active')->count(),'revenue'=>(int)$e->sum('paid_amount'),'status'=>$c->status];
        })->sortByDesc('revenue')->values()->toArray();
    }

    /**
     * Course performance report with date filter.
     */
    public function coursePerformance(Request $request)
    {
        $courses = Course::withCount(['batches', 'subjects'])->get()->map(function ($c) use ($request) {
            $enrollments = Enrollment::whereIn('batch_id', $c->batches->pluck('id'));
            if ($request->from_date) $enrollments->whereDate('created_at', '>=', $request->from_date);
            if ($request->to_date) $enrollments->whereDate('created_at', '<=', $request->to_date);

            return [
                'id' => $c->id,
                'name' => $c->name,
                'code' => $c->code,
                'category' => $c->category,
                'batches' => $c->batches_count,
                'subjects' => $c->subjects_count,
                'total_enrolled' => $enrollments->count(),
                'active' => $enrollments->where('status', 'active')->count(),
                'revenue' => (int) $enrollments->sum('paid_amount'),
                'status' => $c->status,
            ];
        })->sortByDesc('revenue')->values();

        return $this->success($courses);
    }
}
