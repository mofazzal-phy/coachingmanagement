<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            if (!Schema::hasColumn('courses', 'one_time_fee')) {
                $table->decimal('one_time_fee', 12, 2)->nullable()->after('duration_label');
            }
            if (!Schema::hasColumn('courses', 'enrollment_fee')) {
                $table->decimal('enrollment_fee', 12, 2)->default(0)->after('one_time_fee');
            }
        });

        Schema::table('enrollments', function (Blueprint $table) {
            if (!Schema::hasColumn('enrollments', 'enrollment_fee')) {
                $table->decimal('enrollment_fee', 12, 2)->default(0)->after('fee_type');
            }
            if (!Schema::hasColumn('enrollments', 'enrollment_fee_paid')) {
                $table->decimal('enrollment_fee_paid', 12, 2)->default(0)->after('enrollment_fee');
            }
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            foreach (['enrollment_fee_paid', 'enrollment_fee'] as $col) {
                if (Schema::hasColumn('enrollments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('courses', function (Blueprint $table) {
            foreach (['enrollment_fee', 'one_time_fee'] as $col) {
                if (Schema::hasColumn('courses', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
