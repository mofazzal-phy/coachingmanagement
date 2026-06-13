<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('exam_attempts')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        // MySQL does not support partial unique indexes; official attempt uniqueness is enforced in ExamPaperService.
        if ($driver === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS exam_attempts_official_student_routine_unique '
                . 'ON exam_attempts (exam_routine_id, student_id) '
                . 'WHERE is_practice = false'
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('exam_attempts')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS exam_attempts_official_student_routine_unique');
        }
    }
};
