<?php

namespace Modules\Attendance\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Attendance\app\Services\PortalAttendanceService;
use Modules\Core\app\Http\Controllers\BaseApiController;

class MyAttendanceController extends BaseApiController
{
    public function __construct(
        protected PortalAttendanceService $portalAttendance
    ) {}

    /**
     * Get the authenticated user's own attendance (student, teacher, or employee).
     */
    public function show(): JsonResponse
    {
        $profile = $this->portalAttendance->resolveProfile();

        if (!$profile) {
            return $this->notFound('Attendance profile not found for your account. Please contact administration.');
        }

        [$userType, $userId] = $profile;

        return $this->success(
            $this->portalAttendance->getPortalData($userType, $userId)
        );
    }
}
