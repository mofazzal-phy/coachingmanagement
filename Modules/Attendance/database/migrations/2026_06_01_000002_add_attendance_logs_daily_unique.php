<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Attendance\app\Models\AttendanceLog;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('attendance_logs', 'session_key')) {
            Schema::table('attendance_logs', function (Blueprint $table) {
                $table->string('session_key', 36)->nullable()->after('attendance_session_id');
            });
        }

        AttendanceLog::query()
            ->orderByDesc('updated_at')
            ->get()
            ->groupBy(fn ($log) => implode('|', [
                $log->user_type,
                $log->user_id,
                $log->attendance_date?->toDateString(),
                $log->attendance_session_id ?? '00000000-0000-0000-0000-000000000000',
            ]))
            ->each(function ($group) {
                if ($group->count() <= 1) {
                    return;
                }
                $group->slice(1)->each(function (AttendanceLog $duplicate) {
                    $duplicate->studentAttendance?->delete();
                    $duplicate->teacherAttendance?->delete();
                    $duplicate->employeeAttendance?->delete();
                    $duplicate->delete();
                });
            });

        DB::table('attendance_logs')->update([
            'session_key' => DB::raw("COALESCE(attendance_session_id, '00000000-0000-0000-0000-000000000000')"),
        ]);

        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->unique(
                ['user_type', 'user_id', 'attendance_date', 'session_key'],
                'attendance_logs_daily_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropUnique('attendance_logs_daily_unique');
            $table->dropColumn('session_key');
        });
    }
};
