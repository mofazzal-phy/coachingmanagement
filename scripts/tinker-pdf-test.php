<?php

use Modules\Exam\app\Services\ExamAssessmentService;
use Modules\Exam\app\Services\ExamReportPdfService;

$examId = 'fa043250-6bc4-4c87-b73b-675870a490df';
$assessment = app(ExamAssessmentService::class);
$pdfService = app(ExamReportPdfService::class);

foreach (['offline', 'online'] as $channel) {
    $merit = $assessment->computeMeritList($examId, null, null, 20, $channel);
    $pdf = $pdfService->generateMeritListPdf($examId, null, null, 20, $channel);
    $path = storage_path("app/test-merit-{$channel}.pdf");
    file_put_contents($path, $pdf);
    echo "{$channel}: merit_rows=" . count($merit['merit_list']) . " pdf_bytes=" . strlen($pdf) . " header=" . substr($pdf, 0, 5) . " path={$path}\n";
}
