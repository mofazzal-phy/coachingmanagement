<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            if (!Schema::hasColumn('exams', 'eligibility_check_enabled')) {
                $table->boolean('eligibility_check_enabled')->default(false)->after('delivery_mode');
            }
            if (!Schema::hasColumn('exams', 'min_attendance_percent')) {
                $table->decimal('min_attendance_percent', 5, 2)->nullable()->after('eligibility_check_enabled');
            }
        });

        if (!Schema::hasTable('exam_student_eligibility')) {
            Schema::create('exam_student_eligibility', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('exam_id');
                $table->uuid('student_id');
                $table->string('status', 20);
                $table->decimal('attendance_percent', 5, 2)->nullable();
                $table->boolean('is_override')->default(false);
                $table->text('override_reason')->nullable();
                $table->uuid('overridden_by')->nullable();
                $table->timestamp('overridden_at')->nullable();
                $table->timestamp('computed_at')->nullable();
                $table->timestamps();

                $table->unique(['exam_id', 'student_id']);
                $table->foreign('exam_id')->references('id')->on('exams')->cascadeOnDelete();
                $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
                $table->index(['exam_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_student_eligibility');

        Schema::table('exams', function (Blueprint $table) {
            if (Schema::hasColumn('exams', 'min_attendance_percent')) {
                $table->dropColumn('min_attendance_percent');
            }
            if (Schema::hasColumn('exams', 'eligibility_check_enabled')) {
                $table->dropColumn('eligibility_check_enabled');
            }
        });
    }
};
