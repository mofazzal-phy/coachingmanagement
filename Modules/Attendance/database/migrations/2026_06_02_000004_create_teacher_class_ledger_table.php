<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_class_ledger', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('teacher_id');
            $table->uuid('class_session_id');
            $table->uuid('attendance_log_id')->nullable();
            $table->enum('teacher_type', ['permanent', 'contracted', 'guest'])->default('guest');
            $table->uuid('batch_id')->nullable();
            $table->uuid('subject_id')->nullable();
            $table->date('session_date');
            $table->enum('status', ['scheduled', 'completed', 'cancelled', 'no_show'])->default('scheduled');
            $table->decimal('payable_units', 8, 2)->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('class_session_id');
            $table->index(['teacher_id', 'session_date']);
            $table->index(['session_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_class_ledger');
    }
};
