<?php

namespace Modules\Exam\app\Services;

use Modules\Settings\app\Models\Setting;

class LeaderboardSettingsService
{
    public const KEY_STUDENT_TOP_LIMIT = 'leaderboard_student_top_limit';
    public const KEY_ANONYMIZE_NAMES = 'leaderboard_anonymize_names';
    public const KEY_SHOW_PROVISIONAL_MCQ = 'leaderboard_show_provisional_mcq';

    /**
     * @return array{
     *   student_top_limit: int,
     *   anonymize_names: bool,
     *   show_provisional_mcq: bool
     * }
     */
    public function get(): array
    {
        $keys = [
            self::KEY_STUDENT_TOP_LIMIT,
            self::KEY_ANONYMIZE_NAMES,
            self::KEY_SHOW_PROVISIONAL_MCQ,
        ];

        $values = Setting::query()
            ->whereIn('key', $keys)
            ->pluck('value', 'key');

        return [
            'student_top_limit' => max(10, min(100, (int) ($values[self::KEY_STUDENT_TOP_LIMIT] ?? 50))),
            'anonymize_names' => $this->toBool($values[self::KEY_ANONYMIZE_NAMES] ?? '0'),
            'show_provisional_mcq' => $this->toBool($values[self::KEY_SHOW_PROVISIONAL_MCQ] ?? '1'),
        ];
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
