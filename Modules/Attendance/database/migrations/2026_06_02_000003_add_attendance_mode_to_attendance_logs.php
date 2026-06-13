<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->enum('attendance_mode', ['daily', 'session'])
                ->default('daily')
                ->after('attendance_source');
        });

        DB::table('attendance_logs')->update(['attendance_mode' => 'daily']);

        DB::table('attendance_logs')
            ->whereNotNull('attendance_session_id')
            ->where('attendance_session_id', '!=', '00000000-0000-0000-0000-000000000000')
            ->update(['attendance_mode' => 'session']);
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropColumn('attendance_mode');
        });
    }
};
