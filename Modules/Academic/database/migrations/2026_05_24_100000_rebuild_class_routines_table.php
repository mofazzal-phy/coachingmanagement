<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop old table and rebuild with new schema
        Schema::dropIfExists('routine_exceptions');
        Schema::dropIfExists('class_routines');

        Schema::create('class_routines', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Level: batch-level or course-level routines (class_id is optional for backward compat)
            $table->foreignUuid('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignUuid('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->foreignUuid('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignUuid('section_id')->nullable()->constrained('sections')->nullOnDelete();
            // group_id references academic_groups which uses bigint unsigned (not UUID)
            $table->foreignId('group_id')->nullable()->constrained('academic_groups')->nullOnDelete();

            // Core routine data
            $table->foreignUuid('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignUuid('room_id')->nullable()->constrained('rooms')->nullOnDelete();

            // Time slot (direct start/end time instead of period_id FK)
            $table->enum('day_of_week', ['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri']);
            $table->time('start_time');
            $table->time('end_time');

            // Date range for this routine entry
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            // Versioning & status
            $table->integer('version')->default(1);
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');

            // Who created this
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Prevent duplicate: same batch/course/class can't have two routines at same time on same day
            $table->unique(['batch_id', 'course_id', 'class_id', 'section_id', 'group_id', 'day_of_week', 'start_time'], 'routine_unique_slot');

            // Indexes for fast lookups and conflict checking
            $table->index(['teacher_id', 'day_of_week', 'start_time', 'end_time'], 'routine_teacher_time_idx');
            $table->index(['room_id', 'day_of_week', 'start_time', 'end_time'], 'routine_room_time_idx');
            $table->index(['batch_id'], 'routine_batch_idx');
            $table->index(['course_id'], 'routine_course_idx');
            $table->index(['class_id'], 'routine_class_idx');
            $table->index(['status'], 'routine_status_idx');
            $table->index(['day_of_week'], 'routine_day_idx');
        });

        // Re-create routine_exceptions table
        Schema::create('routine_exceptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('class_routine_id')->constrained('class_routines')->cascadeOnDelete();
            $table->date('exception_date');
            $table->enum('type', ['cancelled', 'rescheduled', 'substitute'])->default('cancelled');
            $table->time('new_start_time')->nullable();
            $table->time('new_end_time')->nullable();
            $table->foreignUuid('substitute_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['class_routine_id', 'exception_date'], 'routine_exception_unique');
            $table->index(['exception_date'], 'exception_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_exceptions');
        Schema::dropIfExists('class_routines');

        // Restore original class_routines table
        Schema::create('class_routines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignUuid('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignUuid('section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->foreignUuid('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignUuid('period_id')->constrained('routine_periods')->cascadeOnDelete();
            $table->foreignUuid('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->enum('day_of_week', ['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri']);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['academic_session_id', 'class_id', 'section_id', 'day_of_week', 'period_id'], 'routine_unique_class_period');
            $table->index(['teacher_id', 'day_of_week', 'period_id'], 'routine_teacher_idx');
            $table->index(['room_id', 'day_of_week', 'period_id'], 'routine_room_idx');
        });

        // Restore routine_exceptions
        Schema::create('routine_exceptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('class_routine_id')->constrained('class_routines')->cascadeOnDelete();
            $table->date('exception_date');
            $table->enum('type', ['cancelled', 'rescheduled', 'substitute'])->default('cancelled');
            $table->time('new_start_time')->nullable();
            $table->time('new_end_time')->nullable();
            $table->foreignUuid('substitute_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['class_routine_id', 'exception_date'], 'routine_exception_unique');
        });
    }
};
