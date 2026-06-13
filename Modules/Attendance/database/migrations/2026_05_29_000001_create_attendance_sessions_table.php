<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('class_id')->nullable();
            $table->uuid('course_id')->nullable();
            $table->uuid('batch_id')->nullable();
            $table->uuid('subject_id')->nullable();
            $table->uuid('teacher_id')->nullable();
            $table->uuid('room_id')->nullable();
            $table->uuid('slot_id')->nullable();
            $table->date('attendance_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->enum('session_type', ['student', 'teacher', 'employee'])->default('student');
            $table->enum('source', ['manual', 'routine', 'biometric', 'simulator'])->default('manual');
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'attendance_date']);
            $table->index(['teacher_id', 'attendance_date']);
            $table->index(['status', 'attendance_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('attendance_sessions');
    }
};
