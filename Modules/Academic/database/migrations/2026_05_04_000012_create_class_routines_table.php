<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_routines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignUuid('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignUuid('section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->foreignUuid('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignUuid('period_id')->constrained('routine_periods')->cascadeOnDelete();
            $table->foreignUuid('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->enum('day_of_week', ['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri']);
            $table->string('status')->default('active');
            $table->timestamps();

            // Prevent duplicate: same class/section can't have two routines in same period
            $table->unique(['academic_session_id', 'class_id', 'section_id', 'day_of_week', 'period_id'], 'routine_unique_class_period');

            // Indexes for conflict checking
            $table->index(['teacher_id', 'day_of_week', 'period_id'], 'routine_teacher_idx');
            $table->index(['room_id', 'day_of_week', 'period_id'], 'routine_room_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_routines');
    }
};
