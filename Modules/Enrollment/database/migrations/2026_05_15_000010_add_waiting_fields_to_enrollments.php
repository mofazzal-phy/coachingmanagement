<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            if (!Schema::hasColumn('enrollments', 'waiting_position')) {
                $table->integer('waiting_position')->nullable()->after('status');
            }
            if (!Schema::hasColumn('enrollments', 'priority')) {
                $table->string('priority', 20)->default('normal')->after('waiting_position');
            }
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            foreach (['waiting_position','priority'] as $c) {
                if (Schema::hasColumn('enrollments', $c)) $table->dropColumn($c);
            }
        });
    }
};
