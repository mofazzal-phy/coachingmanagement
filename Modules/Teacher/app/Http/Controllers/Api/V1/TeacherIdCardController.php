<?php

namespace Modules\Teacher\app\Http\Controllers\Api\V1;

use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Teacher\app\Services\TeacherIdCardService;

class TeacherIdCardController extends BaseApiController
{
    public function __construct(
        private readonly TeacherIdCardService $idCardService,
    ) {}

    public function show(string $id)
    {
        return $this->success($this->idCardService->getIdCardData($id));
    }

    public function download(string $id)
    {
        $pdf = $this->idCardService->generatePdfBinary($id);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="teacher-id-' . $id . '.pdf"',
        ]);
    }
}
