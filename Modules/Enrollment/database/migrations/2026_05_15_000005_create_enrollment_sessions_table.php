<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedTinyInteger('current_step')->default(0); // 0=type, 1=student, 2=academic, 3=course, 4=docs, 5=payment
            $table->string('enrollment_type', 20)->nullable(); // new, existing, import
            $table->uuid('student_id')->nullable();
            $table->json('step_data')->nullable();
            $table->string('status', 20)->default('draft'); // draft, in_progress, completed, expired
            $table->datetime('expires_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('students')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_sessions');
    }
};
