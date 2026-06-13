<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('exam_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name'); // e.g., "Mid Term", "Final", "Monthly Test"
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('exams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignUuid('exam_type_id')->constrained('exam_types')->cascadeOnDelete();
            $table->foreignUuid('batch_id')->nullable()->constrained('batches')->cascadeOnDelete();
            $table->foreignUuid('course_id')->nullable()->constrained('courses')->cascadeOnDelete();
            $table->foreignUuid('class_id')->nullable()->constrained('classes')->cascadeOnDelete();
            $table->foreignUuid('section_id')->nullable()->constrained('sections')->cascadeOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'published', 'completed', 'cancelled'])->default('draft');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('exam_routines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignUuid('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->date('exam_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('total_marks')->default(100);
            $table->integer('pass_marks')->default(33);
            $table->timestamps();
        });

        Schema::create('exam_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignUuid('exam_routine_id')->constrained('exam_routines')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->decimal('marks_obtained', 8, 2)->nullable();
            $table->decimal('total_marks', 8, 2);
            $table->string('grade')->nullable();
            $table->decimal('grade_point', 4, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->enum('status', ['pending', 'published', 'absent'])->default('pending');
            $table->foreignUuid('entered_by')->constrained('users');
            $table->timestamps();

            $table->unique(['exam_routine_id', 'student_id'], 'exam_result_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('exam_results');
        Schema::dropIfExists('exam_routines');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('exam_types');
    }
};
