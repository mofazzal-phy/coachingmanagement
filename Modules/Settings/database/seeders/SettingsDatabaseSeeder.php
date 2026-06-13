<?php

namespace Modules\Settings\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\app\Services\GradingService;
use Modules\Settings\app\Models\Setting;

class SettingsDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'key' => 'site_name',
                'value' => 'Coaching Management System',
                'group' => 'general',
                'type' => 'text',
                'description' => 'Institution / coaching center name',
            ],
            [
                'key' => 'site_tagline',
                'value' => 'Excellence in Education',
                'group' => 'general',
                'type' => 'text',
                'description' => 'Short tagline shown on documents',
            ],
            [
                'key' => 'site_email',
                'value' => 'info@coaching.local',
                'group' => 'general',
                'type' => 'text',
                'description' => 'Primary contact email',
            ],
            [
                'key' => 'site_phone',
                'value' => '+880 1XXX-XXXXXX',
                'group' => 'general',
                'type' => 'text',
                'description' => 'Primary contact phone',
            ],
            [
                'key' => 'site_address',
                'value' => 'Dhaka, Bangladesh',
                'group' => 'general',
                'type' => 'text',
                'description' => 'Institution address',
            ],
            [
                'key' => 'academic_year',
                'value' => date('Y'),
                'group' => 'academic',
                'type' => 'text',
                'description' => 'Current academic year label',
            ],
            [
                'key' => 'timezone',
                'value' => 'Asia/Dhaka',
                'group' => 'general',
                'type' => 'text',
                'description' => 'System timezone',
            ],
            [
                'key' => 'currency',
                'value' => 'BDT',
                'group' => 'finance',
                'type' => 'text',
                'description' => 'Default currency code',
            ],
            [
                'key' => GradingService::SETTING_KEY,
                'value' => json_encode(GradingService::defaultRules()),
                'group' => 'academic',
                'type' => 'json',
                'description' => 'Grade letter and grade point thresholds by minimum percentage',
            ],
            [
                'key' => 'attendance_eligibility_eligible_min',
                'value' => '75',
                'group' => 'attendance',
                'type' => 'number',
                'description' => 'Minimum attendance percentage for exam eligibility',
            ],
            [
                'key' => 'attendance_eligibility_warning_min',
                'value' => '60',
                'group' => 'attendance',
                'type' => 'number',
                'description' => 'Minimum attendance percentage for warning status',
            ],
            [
                'key' => 'leaderboard_student_top_limit',
                'value' => '50',
                'group' => 'exam',
                'type' => 'number',
                'description' => 'Maximum leaderboard rows shown to students',
            ],
            [
                'key' => 'leaderboard_anonymize_names',
                'value' => '0',
                'group' => 'exam',
                'type' => 'boolean',
                'description' => 'Hide other students names on student leaderboard (show as Student #rank)',
            ],
            [
                'key' => 'leaderboard_show_provisional_mcq',
                'value' => '1',
                'group' => 'exam',
                'type' => 'boolean',
                'description' => 'Allow unofficial MCQ standings before official result publication',
            ],
        ];

        foreach ($defaults as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
