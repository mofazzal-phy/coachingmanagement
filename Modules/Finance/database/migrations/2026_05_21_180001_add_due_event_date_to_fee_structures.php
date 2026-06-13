<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_structures', function (Blueprint $table) {
            // Add due_day if not exists (was in fillable but never migrated)
            // Add due_date for event-based fee deadlines
            // Add event_date for exam/event start date display
            $table->tinyInteger('due_day')->unsigned()->nullable()->after('amount');
            $table->date('due_date')->nullable()->after('due_day');
            $table->date('event_date')->nullable()->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('fee_structures', function (Blueprint $table) {
            $table->dropColumn(['due_day', 'due_date', 'event_date']);
        });
    }
};
