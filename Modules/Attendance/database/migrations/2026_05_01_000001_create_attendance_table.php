<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('attendance', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('academic_session_id');
            $table->uuid('class_id');
            $table->uuid('section_id');
            $table->uuid('subject_id')->nullable();
            $table->uuid('student_id');
            $table->date('date');
            $table->enum('status', ['present', 'absent', 'late', 'half-day', 'holiday'])->default('present');
            $table->text('remarks')->nullable();
            $table->uuid('marked_by');
            $table->timestamps();

            $table->unique(['student_id', 'date', 'subject_id'], 'attendance_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('attendance');
    }
};
