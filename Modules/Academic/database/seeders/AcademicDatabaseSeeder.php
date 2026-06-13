<?php

namespace Modules\Academic\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Academic\app\Models\Room;
use Modules\Academic\app\Models\RoutinePeriod;
use Modules\Academic\app\Models\AcademicSession;
use Modules\Academic\app\Models\Classes;
use Modules\Academic\app\Models\Section;
use Modules\Academic\app\Models\Subject;
use Modules\Academic\app\Models\AcademicGroup;

class AcademicDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ========== 1. Academic Session ==========
        $session = AcademicSession::firstOrCreate(
            ['name' => '2025-2026'],
            [
                'start_date' => '2025-01-01',
                'end_date'   => '2026-12-31',
                'status'     => 'active',
            ]
        );

        // ========== 2. Academic Groups ==========
        $groups = [
            ['name' => 'Science', 'slug' => 'sci', 'description' => 'Science Group', 'status' => 'active'],
            ['name' => 'Commerce', 'slug' => 'com', 'description' => 'Commerce Group', 'status' => 'active'],
            ['name' => 'Arts', 'slug' => 'art', 'description' => 'Arts Group', 'status' => 'active'],
        ];

        foreach ($groups as $group) {
            AcademicGroup::firstOrCreate(['slug' => $group['slug']], $group);
        }

        $this->command->info('Seeded ' . count($groups) . ' academic groups.');

        // ========== 3. Classes ==========
        $classesList = [
            ['name' => 'Class 6', 'code' => 'CLS-06', 'numeric_value' => 6, 'type' => 'common', 'status' => 'active'],
            ['name' => 'Class 7', 'code' => 'CLS-07', 'numeric_value' => 7, 'type' => 'common', 'status' => 'active'],
            ['name' => 'Class 8', 'code' => 'CLS-08', 'numeric_value' => 8, 'type' => 'common', 'status' => 'active'],
            ['name' => 'Class 9', 'code' => 'CLS-09', 'numeric_value' => 9, 'type' => 'common', 'status' => 'active'],
            ['name' => 'Class 10', 'code' => 'CLS-10', 'numeric_value' => 10, 'type' => 'common', 'status' => 'active'],
        ];

        $classModels = [];
        foreach ($classesList as $cls) {
            $classModels[] = Classes::firstOrCreate(
                ['code' => $cls['code']],
                $cls
            );
        }

        $this->command->info('Seeded ' . count($classModels) . ' classes.');

        // ========== 4. Sections ==========
        foreach ($classModels as $class) {
            $sections = ['A', 'B'];
            foreach ($sections as $sec) {
                Section::firstOrCreate(
                    ['code' => $class->code . '-SEC-' . $sec],
                    [
                        'class_id'  => $class->id,
                        'name'      => 'Section ' . $sec,
                        'status'    => 'active',
                        'capacity'  => 40,
                    ]
                );
            }
        }

        $this->command->info('Seeded sections for all classes.');

        // ========== 5. Subjects ==========
        $commonSubjects = [
            ['name' => 'Bangla', 'code' => 'BAN', 'type' => 'core', 'status' => 'active'],
            ['name' => 'English', 'code' => 'ENG', 'type' => 'core', 'status' => 'active'],
            ['name' => 'Mathematics', 'code' => 'MATH', 'type' => 'core', 'status' => 'active'],
            ['name' => 'General Science', 'code' => 'GSCI', 'type' => 'core', 'status' => 'active'],
            ['name' => 'Social Science', 'code' => 'SSCI', 'type' => 'core', 'status' => 'active'],
            ['name' => 'Islamic Studies', 'code' => 'ISL', 'type' => 'core', 'status' => 'active'],
            ['name' => 'ICT', 'code' => 'ICT', 'type' => 'core', 'status' => 'active'],
            ['name' => 'Physics', 'code' => 'PHY', 'type' => 'elective', 'status' => 'active'],
            ['name' => 'Chemistry', 'code' => 'CHEM', 'type' => 'elective', 'status' => 'active'],
            ['name' => 'Biology', 'code' => 'BIO', 'type' => 'elective', 'status' => 'active'],
            ['name' => 'Accounting', 'code' => 'ACC', 'type' => 'elective', 'status' => 'active'],
            ['name' => 'Business Studies', 'code' => 'BST', 'type' => 'elective', 'status' => 'active'],
            ['name' => 'Geography', 'code' => 'GEO', 'type' => 'elective', 'status' => 'active'],
            ['name' => 'History', 'code' => 'HIS', 'type' => 'elective', 'status' => 'active'],
            ['name' => 'Physical Education', 'code' => 'PED', 'type' => 'optional', 'status' => 'active'],
        ];

        foreach ($commonSubjects as $sub) {
            Subject::firstOrCreate(['code' => $sub['code']], $sub);
        }

        $this->command->info('Seeded ' . count($commonSubjects) . ' subjects.');

        // ========== 6. Rooms ==========
        $rooms = [
            ['name' => 'Room 101', 'code' => 'R101', 'capacity' => 30, 'building' => 'Main Building', 'floor' => '1st Floor', 'has_multimedia' => true, 'status' => 'active'],
            ['name' => 'Room 102', 'code' => 'R102', 'capacity' => 25, 'building' => 'Main Building', 'floor' => '1st Floor', 'has_multimedia' => false, 'status' => 'active'],
            ['name' => 'Room 103', 'code' => 'R103', 'capacity' => 35, 'building' => 'Main Building', 'floor' => '1st Floor', 'has_multimedia' => true, 'status' => 'active'],
            ['name' => 'Room 201', 'code' => 'R201', 'capacity' => 30, 'building' => 'Main Building', 'floor' => '2nd Floor', 'has_multimedia' => true, 'status' => 'active'],
            ['name' => 'Room 202', 'code' => 'R202', 'capacity' => 25, 'building' => 'Main Building', 'floor' => '2nd Floor', 'has_multimedia' => false, 'status' => 'active'],
            ['name' => 'Computer Lab', 'code' => 'CLAB', 'capacity' => 40, 'building' => 'Science Block', 'floor' => 'Ground Floor', 'has_multimedia' => true, 'status' => 'active'],
            ['name' => 'Science Lab', 'code' => 'SLAB', 'capacity' => 35, 'building' => 'Science Block', 'floor' => 'Ground Floor', 'has_multimedia' => false, 'status' => 'active'],
            ['name' => 'Auditorium', 'code' => 'AUDI', 'capacity' => 200, 'building' => 'Main Building', 'floor' => 'Ground Floor', 'has_multimedia' => true, 'status' => 'active'],
        ];

        foreach ($rooms as $room) {
            Room::firstOrCreate(['code' => $room['code']], $room);
        }

        $this->command->info('Seeded ' . count($rooms) . ' rooms.');

        // ========== 7. Routine Periods ==========
        $periods = [
            ['academic_session_id' => $session->id, 'name' => 'Period 1', 'start_time' => '08:00', 'end_time' => '08:45', 'sort_order' => 1, 'is_break' => false, 'status' => 'active'],
            ['academic_session_id' => $session->id, 'name' => 'Period 2', 'start_time' => '08:45', 'end_time' => '09:30', 'sort_order' => 2, 'is_break' => false, 'status' => 'active'],
            ['academic_session_id' => $session->id, 'name' => 'Period 3', 'start_time' => '09:30', 'end_time' => '10:15', 'sort_order' => 3, 'is_break' => false, 'status' => 'active'],
            ['academic_session_id' => $session->id, 'name' => 'Tiffin Break', 'start_time' => '10:15', 'end_time' => '10:30', 'sort_order' => 4, 'is_break' => true, 'status' => 'active'],
            ['academic_session_id' => $session->id, 'name' => 'Period 4', 'start_time' => '10:30', 'end_time' => '11:15', 'sort_order' => 5, 'is_break' => false, 'status' => 'active'],
            ['academic_session_id' => $session->id, 'name' => 'Period 5', 'start_time' => '11:15', 'end_time' => '12:00', 'sort_order' => 6, 'is_break' => false, 'status' => 'active'],
            ['academic_session_id' => $session->id, 'name' => 'Period 6', 'start_time' => '12:00', 'end_time' => '12:45', 'sort_order' => 7, 'is_break' => false, 'status' => 'active'],
            ['academic_session_id' => $session->id, 'name' => 'Lunch Break', 'start_time' => '12:45', 'end_time' => '13:30', 'sort_order' => 8, 'is_break' => true, 'status' => 'active'],
            ['academic_session_id' => $session->id, 'name' => 'Period 7', 'start_time' => '13:30', 'end_time' => '14:15', 'sort_order' => 9, 'is_break' => false, 'status' => 'active'],
            ['academic_session_id' => $session->id, 'name' => 'Period 8', 'start_time' => '14:15', 'end_time' => '15:00', 'sort_order' => 10, 'is_break' => false, 'status' => 'active'],
        ];

        foreach ($periods as $period) {
            RoutinePeriod::firstOrCreate(
                ['academic_session_id' => $period['academic_session_id'], 'sort_order' => $period['sort_order']],
                $period
            );
        }

        $this->command->info('Seeded ' . count($periods) . ' routine periods.');
    }
}