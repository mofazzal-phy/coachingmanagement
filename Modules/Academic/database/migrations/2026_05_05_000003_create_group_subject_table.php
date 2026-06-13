<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create dedicated group_subject pivot table
        // Note: academic_groups uses $table->id() = BIGINT UNSIGNED
        //       subjects uses UUID
        Schema::create('group_subject', function (Blueprint $table) {
            $table->unsignedBigInteger('group_id');
            $table->foreignUuid('subject_id')
                ->constrained('subjects')
                ->cascadeOnDelete();
            $table->primary(['group_id', 'subject_id']);
            $table->timestamps();

            // Add foreign key for group_id referencing academic_groups.id (BIGINT)
            $table->foreign('group_id')
                ->references('id')
                ->on('academic_groups')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_subject');
    }
};
