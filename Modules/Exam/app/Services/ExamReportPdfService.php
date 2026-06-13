<?php

namespace Modules\Exam\app\Services;

use Barryvdh\DomPDF\Facade\Pdf;

class ExamReportPdfService
{
    public function __construct(private readonly ExamAssessmentService $assessmentService)
    {
    }

    public function generateMarksheetPdf(
        string $examId,
        string $studentId,
        ?string $deliveryChannel = null,
    ): string {
        $data = $this->assessmentService->buildMarksheetData($examId, $studentId, $deliveryChannel);

        return Pdf::loadView('exam::marksheet', $data)->output();
    }

    public function generateMeritListPdf(
        string $examId,
        ?string $scopeType,
        ?string $scopeId,
        int $topN,
        ?string $deliveryChannel = 'offline',
    ): string {
        $merit = $this->assessmentService->computeMeritList($examId, $scopeType, $scopeId, $topN, $deliveryChannel);

        return Pdf::loadView('exam::merit-list', [
            'exam' => $merit['exam'],
            'scope' => $merit['scope'],
            'ranking_rule' => $merit['ranking_rule'],
            'merit_list' => $merit['merit_list'],
            'total_students' => $merit['total_students'],
            'generated_at' => now()->format('d M, Y h:i A'),
        ])->output();
    }

    public function generateResultSheetPdf(
        string $examId,
        ?string $batchId = null,
        ?string $deliveryChannel = 'offline',
    ): string {
        $rows = $this->assessmentService->buildResultSheetRows($examId, $batchId, $deliveryChannel);
        $exam = \Modules\Exam\app\Models\Exam::with(['batch', 'course', 'class'])->findOrFail($examId);

        return Pdf::loadView('exam::result-sheet', [
            'exam' => [
                'name' => $exam->name,
                'level_name' => $exam->batch?->name ?? $exam->course?->name ?? $exam->class?->name ?? 'All',
            ],
            'rows' => $rows,
            'generated_at' => now()->format('d M, Y h:i A'),
        ])->output();
    }

    /**
     * @return string CSV content
     */
    public function generateResultSheetCsv(
        string $examId,
        ?string $batchId = null,
        ?string $deliveryChannel = 'offline',
    ): string {
        $rows = $this->assessmentService->buildResultSheetRows($examId, $batchId, $deliveryChannel);
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Rank', 'Roll No', 'Student Name', 'Total Marks', 'Total Possible', 'Percentage', 'GPA', 'Grade', 'Passed']);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['rank'],
                $row['roll_no'] ?? '',
                $row['student_name'],
                $row['total_marks'],
                $row['total_possible'],
                $row['percentage'],
                $row['gpa'],
                $row['overall_grade'],
                $row['passed'],
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv ?: '';
    }
}
