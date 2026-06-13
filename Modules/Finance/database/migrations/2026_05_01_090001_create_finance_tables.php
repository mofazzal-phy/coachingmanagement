<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('fee_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name'); // e.g., "Tuition Fee", "Admission Fee", "Exam Fee"
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->enum('frequency', ['one_time', 'monthly', 'yearly', 'term'])->default('monthly');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('fee_structures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignUuid('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignUuid('fee_type_id')->constrained('fee_types')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['academic_session_id', 'class_id', 'fee_type_id'], 'fee_structure_unique');
        });

        Schema::create('fee_collections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('academic_session_id')->constrained('academic_sessions');
            $table->foreignUuid('fee_type_id')->constrained('fee_types');
            $table->string('invoice_no')->unique();
            $table->decimal('amount', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2);
            $table->decimal('due_amount', 12, 2)->default(0);
            $table->date('due_date');
            $table->date('paid_date')->nullable();
            $table->enum('payment_method', ['cash', 'bank', 'mobile_banking', 'card', 'online'])->default('cash');
            $table->string('transaction_id')->nullable();
            $table->text('remarks')->nullable();
            $table->enum('status', ['paid', 'partial', 'unpaid', 'overdue', 'cancelled'])->default('unpaid');
            $table->foreignUuid('collected_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('expense_category_id')->constrained('expense_categories')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount', 12, 2);
            $table->date('expense_date');
            $table->string('voucher_no')->nullable();
            $table->enum('payment_method', ['cash', 'bank', 'mobile_banking'])->default('cash');
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('fee_collections');
        Schema::dropIfExists('fee_structures');
        Schema::dropIfExists('fee_types');
    }
};
