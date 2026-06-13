<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            if (!Schema::hasColumn('courses', 'created_by')) {
                $table->uuid('created_by')->nullable()->after('status');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('courses', 'updated_by')) {
                $table->uuid('updated_by')->nullable()->after('created_by');
                $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            }
        });

        Schema::table('batches', function (Blueprint $table) {
            if (!Schema::hasColumn('batches', 'created_by')) {
                $table->uuid('created_by')->nullable()->after('status');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('batches', 'updated_by')) {
                $table->uuid('updated_by')->nullable()->after('created_by');
                $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            }
        });

        // Enhance activity logs table
        Schema::table('enrollment_activity_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('enrollment_activity_logs', 'old_values')) {
                $table->json('old_values')->nullable()->after('new_status');
            }
            if (!Schema::hasColumn('enrollment_activity_logs', 'new_values')) {
                $table->json('new_values')->nullable()->after('old_values');
            }
            if (!Schema::hasColumn('enrollment_activity_logs', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('new_values');
            }
            if (!Schema::hasColumn('enrollment_activity_logs', 'model_type')) {
                $table->string('model_type', 100)->nullable()->after('enrollment_id');
            }
            if (!Schema::hasColumn('enrollment_activity_logs', 'model_id')) {
                $table->uuid('model_id')->nullable()->after('model_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('courses', function ($t) {
            foreach (['created_by','updated_by'] as $c) if (Schema::hasColumn('courses',$c)) $t->dropForeign([$c]) && $t->dropColumn($c);
        });
        Schema::table('batches', function ($t) {
            foreach (['created_by','updated_by'] as $c) if (Schema::hasColumn('batches',$c)) $t->dropForeign([$c]) && $t->dropColumn($c);
        });
        Schema::table('enrollment_activity_logs', function ($t) {
            foreach (['old_values','new_values','ip_address','model_type','model_id'] as $c) if (Schema::hasColumn('enrollment_activity_logs',$c)) $t->dropColumn($c);
        });
    }
};
