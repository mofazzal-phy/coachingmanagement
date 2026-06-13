<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            if (!Schema::hasColumn('exams', 'offline_policy_scope')) {
                $table->string('offline_policy_scope', 16)->default('all')->after('online_exam_fee_applicable');
            }
            if (!Schema::hasColumn('exams', 'online_policy_scope')) {
                $table->string('online_policy_scope', 16)->default('all')->after('offline_policy_scope');
            }
        });

        if (!Schema::hasTable('exam_batch_channel_policies')) {
            Schema::create('exam_batch_channel_policies', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('exam_id')->constrained('exams')->cascadeOnDelete();
                $table->foreignUuid('batch_id')->constrained('batches')->cascadeOnDelete();
                $table->string('delivery_channel', 16);
                $table->boolean('eligibility_check_enabled')->default(false);
                $table->decimal('min_attendance_percent', 5, 2)->nullable();
                $table->boolean('exam_fee_applicable')->default(false);
                $table->timestamps();

                $table->unique(['exam_id', 'batch_id', 'delivery_channel'], 'exam_batch_channel_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_batch_channel_policies');

        Schema::table('exams', function (Blueprint $table) {
            if (Schema::hasColumn('exams', 'online_policy_scope')) {
                $table->dropColumn('online_policy_scope');
            }
            if (Schema::hasColumn('exams', 'offline_policy_scope')) {
                $table->dropColumn('offline_policy_scope');
            }
        });
    }
};
