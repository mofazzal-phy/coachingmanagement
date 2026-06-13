<?php

namespace Modules\Enrollment\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Enrollment\app\Models\Course;
use Modules\Enrollment\app\Models\Batch;
use Modules\Enrollment\app\Models\Enrollment;
use Modules\Academic\app\Models\Classes;
use Modules\Academic\app\Models\AcademicGroup;
use Modules\Academic\app\Models\Subject;
use Modules\Academic\app\Models\AcademicSession;
use Modules\Student\app\Models\Student;
use Modules\Teacher\app\Models\Teacher;
use Illuminate\Support\Str;

class EnrollmentDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create Academic Courses
        $classes = Classes::all();
        $groups = AcademicGroup::all();
        $subjects = Subject::all();
        $session = AcademicSession::where('is_current', true)->first();
        $teachers = Teacher::all();

        if ($classes->isEmpty()) {
            $this->command->warn('No classes found. Skipping course creation.');
            return;
        }

        foreach ($classes as $class) {
            $classSubjects = $subjects->where('class_id', $class->id);

            // Create academic course for each class
            $course = Course::create([
                'name' => $class->name . ' Academic Course',
                'slug' => Str::slug($class->name . '-academic-course') . '-' . Str::random(4),
                'code' => 'CRS-ACD-' . strtoupper(Str::random(4)),
                'category' => 'academic',
                'class_id' => $class->id,
                'has_online' => true,
                'has_offline' => true,
                'duration_days' => 365,
                'duration_label' => '1 Year',
                'description' => "Complete academic course for {$class->name} covering all subjects.",
                'short_description' => "Full {$class->name} academic program",
                'is_featured' => true,
                'sort_order' => $class->sort_order ?? 0,
                'status' => 'active',
            ]);

            // Assign subjects to course
            if ($classSubjects->isNotEmpty()) {
                $syncData = [];
                foreach ($classSubjects as $subject) {
                    $syncData[$subject->id] = [
                        'fee' => rand(500, 2000),
                        'is_optional' => false,
                        'is_mandatory' => true,
                        'sort_order' => $subject->sort_order ?? 0,
                    ];
                }
                $course->subjects()->sync($syncData);
            }

            // Create batches for this course
            foreach (['online', 'offline'] as $mode) {
                $batch = Batch::create([
                    'course_id' => $course->id,
                    'name' => $class->name . ' - ' . ucfirst($mode) . ' Batch',
                    'code' => 'BATCH-' . strtoupper(Str::random(8)),
                    'academic_session_id' => $session?->id,
                    'mode' => $mode,
                    'days' => ['Saturday', 'Monday', 'Wednesday'],
                    'start_time' => '09:00:00',
                    'end_time' => '12:00:00',
                    'capacity' => 50,
                    'enrolled_count' => 0,
                    'status' => 'open',
                    'teacher_id' => $teachers->isNotEmpty() ? $teachers->random()->id : null,
                ]);
            }
        }

        // Create Admission Coaching Courses
        $targets = ['Medical (MBBS)', 'Engineering (BUET)', 'University (DU)', 'General'];
        foreach ($targets as $target) {
            $course = Course::create([
                'name' => $target . ' Admission Coaching',
                'slug' => Str::slug($target . '-admission-coaching') . '-' . Str::random(4),
                'code' => 'CRS-ADM-' . strtoupper(Str::random(4)),
                'category' => 'admission_coaching',
                'target' => $target,
                'has_online' => true,
                'has_offline' => true,
                'duration_days' => 180,
                'duration_label' => '6 Months',
                'description' => "Comprehensive {$target} admission coaching program.",
                'short_description' => "{$target} preparation course",
                'is_featured' => true,
                'sort_order' => 10,
                'status' => 'active',
            ]);

            // Create batches
            foreach (['online', 'offline'] as $mode) {
                Batch::create([
                    'course_id' => $course->id,
                    'name' => $target . ' - ' . ucfirst($mode) . ' Batch',
                    'code' => 'BATCH-' . strtoupper(Str::random(8)),
                    'academic_session_id' => $session?->id,
                    'mode' => $mode,
                    'days' => ['Sunday', 'Tuesday', 'Thursday'],
                    'start_time' => '14:00:00',
                    'end_time' => '17:00:00',
                    'capacity' => 40,
                    'enrolled_count' => 0,
                    'status' => 'open',
                    'teacher_id' => $teachers->isNotEmpty() ? $teachers->random()->id : null,
                ]);
            }
        }

        $this->command->info('Enrollment module seeded successfully!');
    }
}
