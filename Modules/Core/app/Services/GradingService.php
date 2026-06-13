<?php

namespace Modules\Core\app\Services;

use Modules\Settings\app\Models\Setting;

class GradingService
{
    public const SETTING_KEY = 'grading_rules';

    /**
     * Default BD-style grading scale (percentage thresholds).
     */
    public static function defaultRules(): array
    {
        return [
            ['min_percent' => 80, 'grade' => 'A+', 'grade_point' => 5.0],
            ['min_percent' => 70, 'grade' => 'A', 'grade_point' => 4.0],
            ['min_percent' => 60, 'grade' => 'A-', 'grade_point' => 3.5],
            ['min_percent' => 50, 'grade' => 'B', 'grade_point' => 3.0],
            ['min_percent' => 40, 'grade' => 'C', 'grade_point' => 2.0],
            ['min_percent' => 33, 'grade' => 'D', 'grade_point' => 1.0],
            ['min_percent' => 0, 'grade' => 'F', 'grade_point' => 0.0],
        ];
    }

    public function getRules(): array
    {
        $setting = Setting::where('key', self::SETTING_KEY)->first();

        if (!$setting || $setting->value === null || $setting->value === '') {
            return self::defaultRules();
        }

        $rules = json_decode($setting->value, true);

        if (!is_array($rules) || empty($rules)) {
            return self::defaultRules();
        }

        usort($rules, fn ($a, $b) => ($b['min_percent'] ?? 0) <=> ($a['min_percent'] ?? 0));

        return $rules;
    }

    /**
     * @return array{grade: string, grade_point: float}
     */
    public function calculateFromPercentage(float $percentage): array
    {
        $percentage = max(0, min(100, $percentage));

        foreach ($this->getRules() as $rule) {
            if ($percentage >= (float) ($rule['min_percent'] ?? 0)) {
                return [
                    'grade' => (string) ($rule['grade'] ?? 'F'),
                    'grade_point' => (float) ($rule['grade_point'] ?? 0),
                ];
            }
        }

        return ['grade' => 'F', 'grade_point' => 0.0];
    }

    /**
     * @return array{grade: string, grade_point: float, percentage: float}
     */
    public function calculateFromMarks(float $marksObtained, float $totalMarks): array
    {
        $percentage = $totalMarks > 0
            ? round(($marksObtained / $totalMarks) * 100, 2)
            : 0.0;

        $result = $this->calculateFromPercentage($percentage);

        return array_merge($result, ['percentage' => $percentage]);
    }

    public function calculateGradeLetter(float $percentage): string
    {
        return $this->calculateFromPercentage($percentage)['grade'];
    }
}
