<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Courses indexes
        Schema::table('courses', function (Blueprint $table) {
            $table->index('category');
            $table->index('status');
            $table->index(['category', 'status']);
        });

        // Batches indexes
        Schema::table('batches', function (Blueprint $table) {
            $table->index('course_id');
            $table->index('status');
            $table->index('mode');
            $table->index('enrolled_count');
            $table->index(['course_id', 'status']);
        });

        // Enrollments indexes
        Schema::table('enrollments', function (Blueprint $table) {
            $table->index('student_id');
            $table->index('batch_id');
            $table->index('status');
            $table->index('payment_status');
            $table->index('enrollment_type');
            $table->index(['status', 'payment_status']);
            $table->index('created_at');
        });

        // Students indexes
        Schema::table('students', function (Blueprint $table) {
            $table->index('phone');
            $table->index('student_id');
            $table->index('status');
        });

        // Activity logs
        Schema::table('enrollment_activity_logs', function (Blueprint $table) {
            $table->index('enrollment_id');
            $table->index('model_type');
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        // Skip — dropping indexes doesn't affect data
    }
};
