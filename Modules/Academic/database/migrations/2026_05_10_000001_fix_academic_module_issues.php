<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add group_id to class_subject if not exists
        Schema::table('class_subject', function (Blueprint $table) {
            if (!Schema::hasColumn('class_subject', 'group_id')) {
                $table->foreignId('group_id')
                    ->nullable()
                    ->constrained('academic_groups')
                    ->nullOnDelete()
                    ->after('subject_id');
            }
        });

        // Add a unique index on (class_id, subject_id, group_id) to prevent duplicates
        try {
            Schema::table('class_subject', function (Blueprint $table) {
                $table->unique(['class_id', 'subject_id', 'group_id'], 'class_subject_unique');
            });
        } catch (\Exception $e) {
            // Index may already exist from a previous partial run
        }

        // 2. Fix class_routines unique constraint to include group_id
        // Drop the foreign key on period_id first (may already be dropped)
        try {
            Schema::table('class_routines', function (Blueprint $table) {
                $table->dropForeign(['period_id']);
            });
        } catch (\Exception $e) {
            // Foreign key may already be dropped
        }

        // Drop and re-add the unique constraint to ensure it has the right columns
        try {
            DB::statement('ALTER TABLE class_routines DROP INDEX routine_unique_class_period');
        } catch (\Exception $e) {
            // Index may already be dropped
        }

        // Add the new unique constraint with group_id included
        try {
            Schema::table('class_routines', function (Blueprint $table) {
                $table->unique(['academic_session_id', 'class_id', 'section_id', 'group_id', 'day_of_week', 'period_id', 'routine_type'], 'routine_unique_class_period');
            });
        } catch (\Exception $e) {
            // May already exist
        }

        // Re-add the foreign key
        try {
            Schema::table('class_routines', function (Blueprint $table) {
                $table->foreign('period_id')
                    ->references('id')
                    ->on('routine_periods')
                    ->cascadeOnDelete();
            });
        } catch (\Exception $e) {
            // May already exist
        }

        // 3. Add unique constraint on routine_periods (academic_session_id, sort_order)
        // First, clean up duplicate entries - keep only the first one for each duplicate group
        $duplicates = DB::select('
            SELECT rp1.id 
            FROM routine_periods rp1
            INNER JOIN routine_periods rp2 
                ON rp1.academic_session_id = rp2.academic_session_id 
                AND rp1.sort_order = rp2.sort_order 
                AND rp1.id > rp2.id
        ');

        foreach ($duplicates as $dup) {
            DB::table('routine_periods')->where('id', $dup->id)->delete();
        }

        // Now add the unique constraint
        try {
            Schema::table('routine_periods', function (Blueprint $table) {
                $table->unique(['academic_session_id', 'sort_order'], 'routine_periods_session_sort_unique');
            });
        } catch (\Exception $e) {
            // May already exist
        }
    }

    public function down(): void
    {
        try {
            Schema::table('class_subject', function (Blueprint $table) {
                $table->dropUnique('class_subject_unique');
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('class_routines', function (Blueprint $table) {
                $table->dropForeign(['period_id']);
            });
        } catch (\Exception $e) {}

        try {
            DB::statement('ALTER TABLE class_routines DROP INDEX routine_unique_class_period');
        } catch (\Exception $e) {}

        try {
            Schema::table('class_routines', function (Blueprint $table) {
                $table->unique(['academic_session_id', 'class_id', 'section_id', 'day_of_week', 'period_id', 'routine_type'], 'routine_unique_class_period');
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('class_routines', function (Blueprint $table) {
                $table->foreign('period_id')
                    ->references('id')
                    ->on('routine_periods')
                    ->cascadeOnDelete();
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('routine_periods', function (Blueprint $table) {
                $table->dropUnique('routine_periods_session_sort_unique');
            });
        } catch (\Exception $e) {}
    }
};
