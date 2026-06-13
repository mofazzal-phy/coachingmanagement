<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_fee_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('student_id');
            $table->string('enrollment_id')->nullable();
            $table->string('fee_structure_id');
            $table->string('title');
            $table->text('message')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->string('status')->default('unread'); // unread, read, paid, expired
            $table->string('type')->default('exam_fee'); // exam_fee, library_fee, other
            $table->json('meta')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('enrollment_id')->references('id')->on('enrollments')->onDelete('set null');
            $table->foreign('fee_structure_id')->references('id')->on('fee_structures')->onDelete('cascade');

            $table->index(['student_id', 'status']);
            $table->index(['student_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_fee_notifications');
    }
};
