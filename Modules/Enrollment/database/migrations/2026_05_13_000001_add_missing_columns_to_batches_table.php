<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            // Shift
            if (!Schema::hasColumn('batches', 'shift')) {
                $table->enum('shift', ['morning', 'afternoon', 'evening'])->nullable()->after('mode');
            }

            // Waiting limit
            if (!Schema::hasColumn('batches', 'waiting_limit')) {
                $table->integer('waiting_limit')->default(0)->nullable()->after('capacity');
            }

            // Start/End dates
            if (!Schema::hasColumn('batches', 'start_date')) {
                $table->date('start_date')->nullable()->after('end_time');
            }
            if (!Schema::hasColumn('batches', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }

            // Created by / Updated by
            if (!Schema::hasColumn('batches', 'created_by')) {
                $table->uuid('created_by')->nullable()->after('teacher_id');
            }
            if (!Schema::hasColumn('batches', 'updated_by')) {
                $table->uuid('updated_by')->nullable()->after('created_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropColumn([
                'shift', 'waiting_limit', 'start_date', 'end_date',
                'created_by', 'updated_by',
            ]);
        });
    }
};
