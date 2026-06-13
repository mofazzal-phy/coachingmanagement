<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('student_attendances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('attendance_log_id');
            $table->uuid('student_id');
            $table->uuid('batch_id')->nullable();
            $table->uuid('subject_id')->nullable();
            $table->uuid('slot_id')->nullable();
            $table->timestamps();

            $table->foreign('attendance_log_id')->references('id')->on('attendance_logs')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->index(['student_id', 'batch_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('student_attendances');
    }
};
