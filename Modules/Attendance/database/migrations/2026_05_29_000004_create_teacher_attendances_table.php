<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('teacher_attendances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('attendance_log_id');
            $table->uuid('teacher_id');
            $table->uuid('subject_id')->nullable();
            $table->uuid('class_id')->nullable();
            $table->timestamps();

            $table->foreign('attendance_log_id')->references('id')->on('attendance_logs')->onDelete('cascade');
            $table->foreign('teacher_id')->references('id')->on('teachers')->onDelete('cascade');
            $table->index(['teacher_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('teacher_attendances');
    }
};
