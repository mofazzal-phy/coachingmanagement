<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_routines', function (Blueprint $table) {
            // Slot naming and duration
            if (!Schema::hasColumn('class_routines', 'slot_name')) {
                $table->string('slot_name', 100)->nullable()->after('end_time')
                    ->comment('Human-readable slot name e.g. Morning 1st Period');
            }
            if (!Schema::hasColumn('class_routines', 'duration')) {
                $table->integer('duration')->nullable()->after('slot_name')
                    ->comment('Duration in minutes');
            }
            if (!Schema::hasColumn('class_routines', 'display_order')) {
                $table->integer('display_order')->default(0)->after('duration')
                    ->comment('Custom display ordering in the grid');
            }

            // Lunch break support
            if (!Schema::hasColumn('class_routines', 'is_lunch_break')) {
                $table->boolean('is_lunch_break')->default(false)->after('display_order')
                    ->comment('Marks a time slot as lunch break for merged row display');
            }

            // Off day support
            if (!Schema::hasColumn('class_routines', 'is_off_day')) {
                $table->boolean('is_off_day')->default(false)->after('is_lunch_break')
                    ->comment('Marks a day as off day for a specific batch/class');
            }
            if (!Schema::hasColumn('class_routines', 'off_day_date')) {
                $table->date('off_day_date')->nullable()->after('is_off_day')
                    ->comment('Specific date for the off day');
            }

            // Recurrence / pattern support
            if (!Schema::hasColumn('class_routines', 'recurrence_pattern')) {
                $table->string('recurrence_pattern', 50)->nullable()->after('off_day_date')
                    ->comment('weekly, biweekly, monthly, or null for one-off');
            }
            if (!Schema::hasColumn('class_routines', 'recurrence_end_date')) {
                $table->date('recurrence_end_date')->nullable()->after('recurrence_pattern')
                    ->comment('When recurrence ends');
            }

            // Notes / description
            if (!Schema::hasColumn('class_routines', 'notes')) {
                $table->text('notes')->nullable()->after('recurrence_end_date')
                    ->comment('Internal notes about this routine slot');
            }
        });

        // Add composite index for batch conflict detection
        Schema::table('class_routines', function (Blueprint $table) {
            $table->index(['batch_id', 'day_of_week', 'start_time', 'end_time'], 'routine_batch_time_idx');
            $table->index(['is_lunch_break'], 'routine_lunch_idx');
            $table->index(['is_off_day', 'off_day_date'], 'routine_offday_idx');
            $table->index(['display_order'], 'routine_display_order_idx');
        });
    }

    public function down(): void
    {
        Schema::table('class_routines', function (Blueprint $table) {
            $table->dropIndex('routine_batch_time_idx');
            $table->dropIndex('routine_lunch_idx');
            $table->dropIndex('routine_offday_idx');
            $table->dropIndex('routine_display_order_idx');
        });

        Schema::table('class_routines', function (Blueprint $table) {
            $columns = [
                'slot_name', 'duration', 'display_order', 'is_lunch_break',
                'is_off_day', 'off_day_date', 'recurrence_pattern',
                'recurrence_end_date', 'notes',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('class_routines', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
