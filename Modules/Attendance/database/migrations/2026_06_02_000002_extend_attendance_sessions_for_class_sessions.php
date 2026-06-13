<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->uuid('routine_id')->nullable()->after('slot_id');
            $table->uuid('class_session_id')->nullable()->after('routine_id');
            $table->enum('scheduled_status', ['scheduled', 'in_progress', 'completed', 'cancelled', 'rescheduled'])
                ->default('scheduled')
                ->after('status');
            $table->uuid('rescheduled_from_id')->nullable()->after('scheduled_status');
            $table->text('cancel_reason')->nullable()->after('rescheduled_from_id');
            $table->unsignedInteger('expected_headcount')->nullable()->after('cancel_reason');

            $table->index('class_session_id');
            $table->index('routine_id');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropIndex(['class_session_id']);
            $table->dropIndex(['routine_id']);
            $table->dropColumn([
                'routine_id',
                'class_session_id',
                'scheduled_status',
                'rescheduled_from_id',
                'cancel_reason',
                'expected_headcount',
            ]);
        });
    }
};
