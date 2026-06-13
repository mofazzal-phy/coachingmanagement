<?php

namespace Modules\Attendance\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Attendance\app\Models\BiometricUserMapping;

/**
 * Prepare fingerprint UID mappings for future device use (no device required now).
 */
class BiometricMappingController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = BiometricUserMapping::query()->orderByDesc('created_at');

        if ($request->filled('device_id')) {
            $query->where('device_id', $request->device_id);
        }

        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        return $this->collectionResponse($query->limit(200)->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'nullable|string|exists:biometric_devices,id',
            'biometric_uid' => 'required|string|max:100|unique:biometric_user_mappings,biometric_uid',
            'user_type' => 'required|in:student,teacher,employee',
            'user_id' => 'required|string',
            'is_active' => 'nullable|boolean',
        ]);

        $mapping = BiometricUserMapping::create($validated);

        return $this->created($mapping, 'Biometric mapping saved');
    }

    public function destroy(string $id): JsonResponse
    {
        BiometricUserMapping::findOrFail($id)->delete();

        return $this->success(null, 'Mapping removed');
    }
}
