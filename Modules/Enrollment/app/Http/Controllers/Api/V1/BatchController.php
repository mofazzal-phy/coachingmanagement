<?php

namespace Modules\Enrollment\app\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Enrollment\app\Models\Batch;
use Modules\Enrollment\app\Models\Course;
use Modules\Enrollment\app\Models\Enrollment;

class BatchController extends BaseApiController
{
    public function index(Request $request)
    {
        $query = Batch::with(['course:id,name,code', 'room:id,name', 'teacher:id,first_name,last_name']);

        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->filled('mode')) {
            $query->where('mode', $request->mode);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $batches = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 15);
        return $this->paginatedResponse($batches);
    }

    public function show($id)
    {
        $batch = Batch::with(['course:id,name,code,category', 'room:id,name', 'teacher.user:id,name', 'academicSession:id,name', 'createdBy:id,name'])->findOrFail($id);
        $batch->available_seats = $batch->availableSeats();
        return $this->success($batch);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'course_id' => 'required|exists:courses,id',
                'name' => 'required|string|max:255',
                'academic_session_id' => 'nullable|exists:academic_sessions,id',
                'mode' => 'required|in:online,offline,hybrid',
                'shift' => 'nullable|in:morning,afternoon,evening',
                'room_id' => 'nullable|exists:rooms,id',
                'campus_location' => 'nullable|string|max:255',
                'platform' => 'nullable|string|max:255',
                'meeting_link' => 'nullable|string|max:500',
                'recording_available' => 'boolean',
                'days' => 'nullable|array',
                'start_time' => 'nullable|date_format:H:i',
                'end_time' => 'nullable|date_format:H:i',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date',
                'capacity' => 'required|integer|min:1',
                'waiting_limit' => 'nullable|integer|min:0',
                'status' => 'nullable|sometimes|in:open,closed,full,upcoming',
                'teacher_id' => 'nullable|exists:teachers,id',
            ]);

            $course = Course::findOrFail($validated['course_id']);
            $validated['code'] = 'BATCH-' . strtoupper(Str::random(8));

            $batch = Batch::create($validated);

            return $this->created($batch->load(['course', 'room', 'teacher']), 'Batch created successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[BatchController::store] Failed to create batch', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except([]),
            ]);
            return $this->error($e->getMessage(), 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $batch = Batch::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'academic_session_id' => 'nullable|exists:academic_sessions,id',
                'mode' => 'sometimes|in:online,offline,hybrid',
                'shift' => 'nullable|in:morning,afternoon,evening',
                'room_id' => 'nullable|exists:rooms,id',
                'campus_location' => 'nullable|string|max:255',
                'platform' => 'nullable|string|max:255',
                'meeting_link' => 'nullable|string|max:500',
                'recording_available' => 'boolean',
                'days' => 'nullable|array',
                'start_time' => 'nullable|date_format:H:i',
                'end_time' => 'nullable|date_format:H:i',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date',
                'capacity' => 'sometimes|integer|min:1',
                'waiting_limit' => 'nullable|integer|min:0',
                'status' => 'nullable|sometimes|in:open,closed,full,upcoming',
                'teacher_id' => 'nullable|exists:teachers,id',
            ]);

            $batch->update($validated);

            return $this->success($batch->fresh()->load(['course', 'room', 'teacher']), 'Batch updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[BatchController::update] Failed to update batch', [
                'batch_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error($e->getMessage(), 500);
        }
    }

    public function destroy($id)
    {
        $batch = Batch::findOrFail($id);
        $batch->delete();

        return $this->noContent('Batch deleted successfully');
    }

    public function byCourse($courseId)
    {
        $batches = Batch::where('course_id', $courseId)
            ->with(['room', 'teacher'])
            ->get()
            ->map(function ($batch) {
                $batch->available_seats = $batch->availableSeats();
                return $batch;
            });

        return $this->success($batches);
    }

    public function students($id)
    {
        $batch = Batch::with(['enrollments.student', 'enrollments.subjects'])->findOrFail($id);
        return $this->success($batch);
    }

    public function statistics()
    {
        try {
            $total = Batch::count();

            // Compute status counts from actual enrollment data for accuracy
            $batches = Batch::select(['id', 'capacity', 'status'])->get();
            $enrollmentCounts = Enrollment::whereIn('status', ['active', 'pending'])
                ->select('batch_id', DB::raw('count(*) as total'))
                ->groupBy('batch_id')
                ->pluck('total', 'batch_id');

            $openCount = 0;
            $fullCount = 0;
            $closedCount = 0;
            $upcomingCount = 0;
            $totalEnrolled = 0;
            $totalCapacity = 0;

            foreach ($batches as $batch) {
                $capacity = (int) ($batch->capacity ?? 0);
                $totalCapacity += $capacity;
                $actualEnrolled = (int) ($enrollmentCounts[$batch->id] ?? 0);
                $totalEnrolled += $actualEnrolled;

                // Determine effective status based on actual enrollment vs capacity
                $isActuallyFull = $capacity > 0 && $actualEnrolled >= $capacity;

                switch ($batch->status) {
                    case 'closed':
                        $closedCount++;
                        break;
                    case 'upcoming':
                        $upcomingCount++;
                        break;
                    case 'full':
                        // Only count as full if it's actually full, otherwise treat as open
                        if ($isActuallyFull) {
                            $fullCount++;
                        } else {
                            $openCount++;
                        }
                        break;
                    case 'open':
                    default:
                        if ($isActuallyFull) {
                            $fullCount++;
                        } else {
                            $openCount++;
                        }
                        break;
                }
            }

            $avgOccupancy = $totalCapacity > 0 ? round(($totalEnrolled / $totalCapacity) * 100) : 0;

            return $this->success([
                'total_batches' => $total,
                'open_batches' => $openCount,
                'full_batches' => $fullCount,
                'closed_batches' => $closedCount,
                'upcoming_batches' => $upcomingCount,
                'total_enrolled' => $totalEnrolled,
                'total_capacity' => $totalCapacity,
                'avg_occupancy' => $avgOccupancy,
            ]);
        } catch (\Exception $e) {
            Log::error('Batch statistics failed: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return $this->success([
                'total_batches' => Batch::count(),
                'open_batches' => 0,
                'full_batches' => 0,
                'closed_batches' => 0,
                'upcoming_batches' => 0,
                'total_enrolled' => 0,
                'total_capacity' => 0,
                'avg_occupancy' => 0,
            ]);
        }
    }

    public function bulkAction(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:batches,id',
            'action' => 'required|in:delete,close,reopen',
        ]);

        $count = 0;
        foreach ($validated['ids'] as $id) {
            $batch = Batch::withTrashed()->find($id);
            if (!$batch) continue;
            switch ($validated['action']) {
                case 'delete': $batch->delete(); break;
                case 'close': $batch->update(['status' => 'closed']); break;
                case 'reopen': $batch->update(['status' => 'open']); break;
            }
            $count++;
        }
        return $this->success(compact('count'), "{$count} batches {$validated['action']}d");
    }

    public function export(Request $request)
    {
        $batches = Batch::with(['course', 'teacher'])->orderBy('created_at', 'desc')->get();
        $headers = ['Name','Code','Course','Mode','Days','Time','Teacher','Seats','Enrolled','Status'];
        $rows = [];
        foreach ($batches as $b) {
            $rows[] = [$b->name,$b->code,$b->course?->name??'',$b->mode,implode('|',$b->days??[]),($b->start_time??'').'-'.($b->end_time??''),$b->teacher?implode(' ',[$b->teacher->first_name??'',$b->teacher->last_name??'']):'',$b->capacity,$b->enrolled_count,$b->status];
        }
        $csv = implode(',',$headers)."\n";
        foreach ($rows as $r) $csv .= '"'.implode('","',$r)."\"\n";
        return response($csv, 200, ['Content-Type'=>'text/csv','Content-Disposition'=>'attachment; filename="batches-export.csv"']);
    }

    public function restore($id)
    {
        $batch = Batch::withTrashed()->findOrFail($id);
        $batch->restore();
        return $this->success($batch, 'Batch restored');
    }

    public function duplicate($id)
    {
        $original = Batch::findOrFail($id);
        $new = $original->replicate();
        $new->name = $original->name.' (Copy)';
        $new->code = 'BATCH-'.strtoupper(\Illuminate\Support\Str::random(6));
        $new->enrolled_count = 0;
        $new->status = 'upcoming';
        $new->save();
        return $this->created($new, 'Batch duplicated');
    }
}
