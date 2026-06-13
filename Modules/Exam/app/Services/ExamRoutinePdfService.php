<?php

namespace Modules\Exam\app\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Modules\Exam\app\Models\Exam;
use Modules\Exam\app\Models\ExamRoutine;

class ExamRoutinePdfService
{
    /**
     * Generate a PDF of the full exam routine schedule.
     */
    public function generateRoutinePdf(string $examId): string
    {
        $exam = Exam::with(['examType', 'batch', 'course', 'class'])->findOrFail($examId);

        $routines = ExamRoutine::with(['subject', 'room', 'teacher'])
            ->where('exam_id', $examId)
            ->orderBy('exam_date')
            ->orderBy('start_time')
            ->get();

        $grouped = $routines->groupBy(function ($routine) {
            return Carbon::parse($routine->exam_date)->format('Y-m-d');
        });

        $data = [
            'exam' => [
                'name' => $exam->name,
                'type' => $exam->examType?->name ?? 'N/A',
                'level' => $exam->batch_id ? 'Batch' : ($exam->course_id ? 'Course' : 'Class'),
                'level_name' => $exam->batch?->name ?? $exam->course?->name ?? $exam->class?->name ?? 'N/A',
                'start_date' => $routines->isNotEmpty()
                    ? Carbon::parse($routines->min('exam_date'))->format('d M, Y')
                    : 'N/A',
                'end_date' => $routines->isNotEmpty()
                    ? Carbon::parse($routines->max('exam_date'))->format('d M, Y')
                    : 'N/A',
            ],
            'grouped' => $grouped->map(function ($dayRoutines, $date) {
                return [
                    'date' => Carbon::parse($date)->format('l, d M, Y'),
                    'routines' => $dayRoutines->map(function ($routine) {
                        return [
                            'subject' => $routine->subject?->name ?? 'N/A',
                            'subject_code' => $routine->subject?->code ?? '',
                            'start_time' => $routine->start_time_formatted,
                            'end_time' => $routine->end_time_formatted,
                            'room' => $routine->room?->name ?? 'TBD',
                            'teacher' => $routine->teacher?->full_name ?? 'TBD',
                            'total_marks' => $routine->total_marks ?? 'N/A',
                            'pass_marks' => $routine->pass_marks ?? 'N/A',
                        ];
                    }),
                ];
            })->values(),
            'total_days' => $grouped->count(),
            'total_subjects' => $routines->count(),
            'generated_at' => now()->format('d M, Y h:i A'),
        ];

        $pdf = Pdf::loadView('exam::exam-routine-pdf', $data);
        $filename = 'exam-routine-' . $examId . '.pdf';

        return $pdf->output();
    }

    /**
     * Generate and save the PDF, returning the path.
     */
    public function generateAndSave(string $examId): string
    {
        $output = $this->generateRoutinePdf($examId);
        $filename = 'exam-routine-' . $examId . '.pdf';
        $path = 'exam-routines/' . $filename;

        \Illuminate\Support\Facades\Storage::put($path, $output);

        return $path;
    }
}
