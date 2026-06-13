<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_type', 50); // student, teacher, employee
            $table->uuid('user_id');
            $table->enum('attendance_source', ['manual', 'biometric', 'simulator', 'qr', 'rfid', 'face'])->default('manual');
            $table->enum('attendance_status', ['present', 'absent', 'late', 'leave', 'half_day'])->default('present');
            $table->timestamp('check_in')->nullable();
            $table->timestamp('check_out')->nullable();
            $table->date('attendance_date');
            $table->uuid('device_id')->nullable();
            $table->uuid('attendance_session_id')->nullable();
            $table->text('remarks')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index(['user_type', 'user_id', 'attendance_date']);
            $table->index(['attendance_session_id']);
            $table->index(['attendance_date', 'attendance_status']);
            $table->index(['device_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('attendance_logs');
    }
};
