<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('enrollment_no')->unique();
            $table->uuid('student_id');
            $table->uuid('batch_id');
            $table->uuid('academic_session_id')->nullable();

            // Type
            $table->enum('enrollment_type', ['new', 'renewal'])->default('new');
            $table->uuid('previous_enrollment_id')->nullable();

            // Academic snapshot
            $table->uuid('enrolled_class_id')->nullable();
            $table->unsignedBigInteger('enrolled_group_id')->nullable();

            // Mode
            $table->enum('mode', ['online', 'offline', 'hybrid']);

            // Fee
            $table->decimal('total_fee', 10, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->string('discount_reason')->nullable();
            $table->decimal('payable_fee', 10, 2)->default(0);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('due_amount', 10, 2)->default(0);
            $table->enum('payment_status', ['pending', 'partial', 'paid'])->default('pending');

            // Status
            $table->enum('status', ['pending', 'active', 'completed', 'dropped'])->default('pending');
            $table->timestamp('enrolled_at')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            // Guardian contact
            $table->string('guardian_phone')->nullable();
            $table->string('guardian_email')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('batch_id')->references('id')->on('batches')->onDelete('cascade');
            $table->foreign('academic_session_id')->references('id')->on('academic_sessions')->onDelete('set null');
            $table->foreign('enrolled_class_id')->references('id')->on('classes')->onDelete('set null');
            $table->foreign('enrolled_group_id')->references('id')->on('academic_groups')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
