<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('student_fee_assignments', function (Blueprint $table) {
            $table->string('period_month', 7)->nullable()->after('due_date')
                ->comment('Calendar month this fee covers, e.g. 2026-01 for January 2026');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_fee_assignments', function (Blueprint $table) {
            $table->dropColumn('period_month');
        });
    }
};
