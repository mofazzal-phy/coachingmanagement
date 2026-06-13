<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add batch_id and course_id to class_routines (nullable — class-level routines don't need them)
        Schema::table('class_routines', function (Blueprint $table) {
            if (!Schema::hasColumn('class_routines', 'batch_id')) {
                $table->foreignUuid('batch_id')->nullable()->after('group_id')
                    ->constrained('batches')->nullOnDelete();
            }
            if (!Schema::hasColumn('class_routines', 'course_id')) {
                $table->foreignUuid('course_id')->nullable()->after('batch_id')
                    ->constrained('courses')->nullOnDelete();
            }
            if (!Schema::hasColumn('class_routines', 'version')) {
                $table->integer('version')->default(1)->after('status');
            }
            if (!Schema::hasColumn('class_routines', 'start_date')) {
                $table->date('start_date')->nullable()->after('version');
            }
            if (!Schema::hasColumn('class_routines', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }
            if (!Schema::hasColumn('class_routines', 'created_by')) {
                $table->foreignUuid('created_by')->nullable()->after('end_date')
                    ->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('class_routines', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('created_by');
            }
        });

        // Change status column from string to enum
        DB::statement("ALTER TABLE `class_routines` MODIFY `status` ENUM('active','inactive','draft','published','archived') NOT NULL DEFAULT 'active'");

        // Add indexes for new columns
        Schema::table('class_routines', function (Blueprint $table) {
            $table->index(['batch_id'], 'routine_batch_idx');
            $table->index(['course_id'], 'routine_course_idx');
            $table->index(['status'], 'routine_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('class_routines', function (Blueprint $table) {
            $table->dropIndex('routine_batch_idx');
            $table->dropIndex('routine_course_idx');
            $table->dropIndex('routine_status_idx');
            $table->dropConstrainedForeignId('batch_id');
            $table->dropConstrainedForeignId('course_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('version');
            $table->dropColumn('start_date');
            $table->dropColumn('end_date');
            $table->dropColumn('sort_order');
        });

        DB::statement("ALTER TABLE `class_routines` MODIFY `status` VARCHAR(255) NOT NULL DEFAULT 'active'");
    }
};
