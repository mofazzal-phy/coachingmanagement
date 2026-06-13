<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('exams')) {
            return;
        }

        Schema::table('exams', function (Blueprint $table) {
            if (!Schema::hasColumn('exams', 'online_eligibility_check_enabled')) {
                $table->boolean('online_eligibility_check_enabled')->default(false)->after('exam_fee_applicable');
            }
            if (!Schema::hasColumn('exams', 'online_min_attendance_percent')) {
                $table->decimal('online_min_attendance_percent', 5, 2)->nullable()->after('online_eligibility_check_enabled');
            }
            if (!Schema::hasColumn('exams', 'online_exam_fee_applicable')) {
                $table->boolean('online_exam_fee_applicable')->default(false)->after('online_min_attendance_percent');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('exams')) {
            return;
        }

        Schema::table('exams', function (Blueprint $table) {
            foreach (['online_exam_fee_applicable', 'online_min_attendance_percent', 'online_eligibility_check_enabled'] as $col) {
                if (Schema::hasColumn('exams', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
