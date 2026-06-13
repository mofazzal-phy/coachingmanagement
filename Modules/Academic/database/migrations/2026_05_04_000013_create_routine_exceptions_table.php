<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routine_exceptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignUuid('class_routine_id')->nullable()->constrained('class_routines')->nullOnDelete();
            $table->foreignUuid('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignUuid('section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->date('exception_date');
            $table->enum('exception_type', ['holiday', 'extra_class', 'cancellation', 'reschedule']);
            $table->foreignUuid('original_subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignUuid('substitute_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignUuid('new_period_id')->nullable()->constrained('routine_periods')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['exception_date', 'class_id', 'section_id'], 'exception_date_class_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_exceptions');
    }
};
