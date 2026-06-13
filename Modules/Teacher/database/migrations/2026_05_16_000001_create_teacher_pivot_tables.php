<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('subject_teacher')) {
            Schema::create('subject_teacher', function (Blueprint $table) {
                $table->uuid('teacher_id');
                $table->uuid('subject_id');
                $table->primary(['teacher_id', 'subject_id']);
                $table->foreign('teacher_id')->references('id')->on('teachers')->cascadeOnDelete();
                $table->foreign('subject_id')->references('id')->on('subjects')->cascadeOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('class_teacher')) {
            Schema::create('class_teacher', function (Blueprint $table) {
                $table->uuid('teacher_id');
                $table->uuid('class_id');
                $table->uuid('academic_session_id')->nullable();
                $table->primary(['teacher_id', 'class_id']);
                $table->foreign('teacher_id')->references('id')->on('teachers')->cascadeOnDelete();
                $table->foreign('class_id')->references('id')->on('classes')->cascadeOnDelete();
                $table->foreign('academic_session_id')->references('id')->on('academic_sessions')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_teacher');
        Schema::dropIfExists('class_teacher');
    }
};
