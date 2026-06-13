<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_subject', function (Blueprint $table) {
            $table->decimal('monthly_fee', 10, 2)->default(0)->after('fee');
        });
    }

    public function down(): void
    {
        Schema::table('course_subject', function (Blueprint $table) {
            $table->dropColumn('monthly_fee');
        });
    }
};
