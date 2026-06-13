<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            if (!Schema::hasColumn('exams', 'is_practice')) {
                $table->boolean('is_practice')->default(false)->after('status');
            }
            if (!Schema::hasColumn('exams', 'delivery_mode')) {
                $table->string('delivery_mode', 20)->default('offline')->after('is_practice');
            }
        });

        Schema::table('exam_routines', function (Blueprint $table) {
            if (!Schema::hasColumn('exam_routines', 'duration_minutes')) {
                $table->unsignedSmallInteger('duration_minutes')->nullable()->after('pass_marks');
            }
            if (!Schema::hasColumn('exam_routines', 'randomize_questions')) {
                $table->boolean('randomize_questions')->default(false)->after('duration_minutes');
            }
        });

        if (!Schema::hasTable('exam_routine_questions')) {
            Schema::create('exam_routine_questions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('exam_routine_id');
                $table->uuid('question_id');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->decimal('marks_override', 8, 2)->nullable();
                $table->timestamps();

                $table->foreign('exam_routine_id')->references('id')->on('exam_routines')->cascadeOnDelete();
                $table->foreign('question_id')->references('id')->on('questions')->cascadeOnDelete();
                $table->unique(['exam_routine_id', 'question_id']);
                $table->index(['exam_routine_id', 'sort_order']);
            });
        }

        if (!Schema::hasTable('exam_attempts')) {
            Schema::create('exam_attempts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('exam_routine_id');
                $table->uuid('student_id');
                $table->boolean('is_practice')->default(true);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('last_saved_at')->nullable();
                $table->json('answers')->nullable();
                $table->decimal('score', 8, 2)->nullable();
                $table->decimal('total_marks', 8, 2)->nullable();
                $table->string('status', 20)->default('in_progress');
                $table->timestamps();

                $table->foreign('exam_routine_id')->references('id')->on('exam_routines')->cascadeOnDelete();
                $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
                $table->index(['student_id', 'is_practice', 'status']);
                $table->index(['exam_routine_id', 'student_id']);
            });
        }

        Schema::table('exam_types', function (Blueprint $table) {
            if (!Schema::hasColumn('exam_types', 'category')) {
                $table->string('category', 50)->nullable()->after('code');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempts');
        Schema::dropIfExists('exam_routine_questions');

        Schema::table('exam_routines', function (Blueprint $table) {
            if (Schema::hasColumn('exam_routines', 'randomize_questions')) {
                $table->dropColumn('randomize_questions');
            }
            if (Schema::hasColumn('exam_routines', 'duration_minutes')) {
                $table->dropColumn('duration_minutes');
            }
        });

        Schema::table('exams', function (Blueprint $table) {
            if (Schema::hasColumn('exams', 'delivery_mode')) {
                $table->dropColumn('delivery_mode');
            }
            if (Schema::hasColumn('exams', 'is_practice')) {
                $table->dropColumn('is_practice');
            }
        });

        Schema::table('exam_types', function (Blueprint $table) {
            if (Schema::hasColumn('exam_types', 'category')) {
                $table->dropColumn('category');
            }
        });
    }
};
