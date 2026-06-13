<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('routine_id')->nullable();
            $table->uuid('batch_id')->nullable();
            $table->uuid('course_id')->nullable();
            $table->uuid('class_id')->nullable();
            $table->uuid('subject_id')->nullable();
            $table->uuid('teacher_id')->nullable();
            $table->uuid('room_id')->nullable();
            $table->uuid('slot_id')->nullable();
            $table->date('session_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled', 'rescheduled'])->default('scheduled');
            $table->enum('source', ['routine', 'manual', 'exception'])->default('routine');
            $table->uuid('attendance_session_id')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->uuid('rescheduled_from_id')->nullable();
            $table->timestamps();

            $table->index(['session_date', 'batch_id']);
            $table->index(['teacher_id', 'session_date']);
            $table->index(['status', 'session_date']);
            $table->unique(['routine_id', 'session_date'], 'class_sessions_routine_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_sessions');
    }
};
