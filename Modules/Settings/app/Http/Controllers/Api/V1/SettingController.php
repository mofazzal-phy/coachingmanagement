<?php

namespace Modules\Settings\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Settings\app\Models\Setting;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Core\app\Services\GradingService;

class SettingController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $settings = Setting::filter($request->only(['group']))->get()->pluck('value', 'key');
        return $this->success($settings);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'nullable',
            'settings.*.group' => 'sometimes|string',
            'settings.*.type' => 'sometimes|string',
        ]);

        foreach ($validated['settings'] as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'] ?? '',
                    'group' => $setting['group'] ?? 'general',
                    'type' => $setting['type'] ?? 'text',
                ]
            );
        }

        return $this->success(null, 'Settings updated successfully');
    }

    public function get(string $key): JsonResponse
    {
        $setting = Setting::where('key', $key)->first();
        if (!$setting) return $this->notFound('Setting not found');

        if ($setting->type === 'json' && is_string($setting->value)) {
            $decoded = json_decode($setting->value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $setting = clone $setting;
                $setting->value = $decoded;
            }
        }

        return $this->success($setting);
    }

    public function groups(): JsonResponse
    {
        $groups = Setting::select('group')->distinct()->pluck('group');
        return $this->collectionResponse($groups);
    }

    public function gradingRules(): JsonResponse
    {
        return $this->success([
            'key' => GradingService::SETTING_KEY,
            'rules' => app(GradingService::class)->getRules(),
        ]);
    }

    public function updateGradingRules(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rules' => 'required|array|min:1',
            'rules.*.min_percent' => 'required|numeric|min:0|max:100',
            'rules.*.grade' => 'required|string|max:10',
            'rules.*.grade_point' => 'required|numeric|min:0|max:5',
        ]);

        Setting::updateOrCreate(
            ['key' => GradingService::SETTING_KEY],
            [
                'value' => json_encode($validated['rules']),
                'group' => 'academic',
                'type' => 'json',
                'description' => 'Grade letter and grade point thresholds by minimum percentage',
            ]
        );

        return $this->success(
            ['rules' => app(GradingService::class)->getRules()],
            'Grading rules updated successfully'
        );
    }
}
