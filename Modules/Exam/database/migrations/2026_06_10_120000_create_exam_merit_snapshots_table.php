<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_merit_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('exam_id');
            $table->string('delivery_channel', 20);
            $table->string('scope_type', 20)->nullable();
            $table->uuid('scope_id')->nullable();
            $table->timestamp('channel_published_at')->nullable();
            $table->unsignedInteger('total_students')->default(0);
            $table->string('ranking_rule', 30)->default('competition');
            $table->json('scope_meta')->nullable();
            $table->json('merit_list');
            $table->json('subject_toppers')->nullable();
            $table->timestamp('computed_at');
            $table->timestamps();

            $table->foreign('exam_id')->references('id')->on('exams')->cascadeOnDelete();
            $table->unique(
                ['exam_id', 'delivery_channel', 'scope_type', 'scope_id'],
                'exam_merit_snapshots_unique_scope'
            );
            $table->index(['exam_id', 'delivery_channel'], 'exam_merit_snapshots_exam_channel_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_merit_snapshots');
    }
};
