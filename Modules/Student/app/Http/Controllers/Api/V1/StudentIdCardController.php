<?php

namespace Modules\Student\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Student\app\Services\StudentIdCardService;

class StudentIdCardController extends BaseApiController
{
    public function __construct(
        private readonly StudentIdCardService $idCardService,
    ) {}

    public function show(string $id, Request $request): JsonResponse
    {
        $data = $this->idCardService->getIdCardData($id, $request->query('enrollment_id'));

        return $this->success($data);
    }

    public function download(string $id, Request $request)
    {
        $pdf = $this->idCardService->generatePdfBinary($id, $request->query('enrollment_id'));

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="student-id-' . $id . '.pdf"',
        ]);
    }
}
