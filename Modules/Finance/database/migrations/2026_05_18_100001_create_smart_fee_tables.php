<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ============================================
        // 1. DISCOUNT RULES - Configurable discount engine
        // ============================================
        Schema::create('discount_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->enum('condition_type', [
                'early_bird', 'sibling', 'loyalty', 'merit', 'bulk', 'need_based', 'custom'
            ]);
            $table->json('condition_config'); // {"days_before":15,"percentage":10}
            $table->enum('discount_type', ['percentage', 'fixed']);
            $table->decimal('discount_value', 12, 2);
            $table->decimal('max_cap', 12, 2)->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('stackable')->default(false);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamps();
        });

        // ============================================
        // 2. LATE FEE RULES - Configurable late fee engine
        // ============================================
        Schema::create('late_fee_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->enum('calculation_type', ['flat_per_day', 'percentage_per_day', 'tiered']);
            $table->decimal('flat_rate', 12, 2)->nullable();
            $table->decimal('percentage_rate', 5, 2)->nullable();
            $table->json('tier_config')->nullable(); // [{"from":0,"to":7,"rate":0},{"from":8,"to":30,"rate":50}]
            $table->integer('grace_period_days')->default(0);
            $table->decimal('max_cap', 12, 2)->nullable();
            $table->enum('recurring', ['none', 'monthly'])->default('none');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamps();
        });

        // ============================================
        // 3. INSTALLMENT PLANS - Configurable installment engine
        // ============================================
        Schema::create('installment_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->enum('plan_type', ['equal', 'custom', 'percentage']);
            $table->integer('total_installments')->unsigned();
            $table->integer('frequency_days')->unsigned();
            $table->json('config'); // [{"amount":2500,"due_offset_days":0},{"amount":2500,"due_offset_days":30}]
            $table->foreignUuid('late_fee_rule_id')->nullable()->constrained('late_fee_rules');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamps();
        });

        // ============================================
        // 4. STUDENT FEE ASSIGNMENTS - Links fee structures to enrollments
        // ============================================
        Schema::create('student_fee_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->foreignUuid('fee_structure_id')->constrained('fee_structures')->cascadeOnDelete();
            $table->decimal('original_amount', 12, 2);
            $table->decimal('discounted_amount', 12, 2)->nullable();
            $table->decimal('final_amount', 12, 2);
            $table->date('due_date');
            $table->enum('status', ['pending', 'partial', 'paid', 'overdue', 'waived'])->default('pending');
            $table->decimal('late_fee_applied', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->virtualAs('(final_amount + late_fee_applied - paid_amount)');
            $table->foreignUuid('installment_plan_id')->nullable()->constrained('installment_plans');
            $table->integer('installment_number')->nullable();
            $table->timestamps();

            $table->unique(['enrollment_id', 'fee_structure_id'], 'student_fee_unique');
            $table->index('due_date');
            $table->index('status');
        });

        // ============================================
        // 5. PAYMENT TRANSACTIONS - Every payment attempt logged
        // ============================================
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('enrollment_id')->constrained('enrollments');
            $table->foreignUuid('student_id')->constrained('students');
            $table->string('transaction_no')->unique();
            $table->decimal('amount', 12, 2);
            $table->enum('payment_method', ['bkash', 'nagad', 'rocket', 'bank', 'cash', 'card', 'check']);
            $table->string('gateway_trx_id', 100)->nullable();
            $table->string('reference_no', 100)->nullable();
            $table->enum('status', ['pending', 'confirmed', 'rejected', 'refunded'])->default('pending');
            $table->foreignUuid('confirmed_by')->nullable()->constrained('users');
            $table->timestamp('confirmed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('is_manual')->default(false); // true = admin recorded manually
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });

        // ============================================
        // 6. PAYMENT ALLOCATIONS - How a payment is split across fees
        // ============================================
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transaction_id')->constrained('payment_transactions')->cascadeOnDelete();
            $table->foreignUuid('fee_assignment_id')->constrained('student_fee_assignments');
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->index('transaction_id');
            $table->index('fee_assignment_id');
        });

        // ============================================
        // 7. FEE AUDIT LOGS - Every change tracked
        // ============================================
        Schema::create('fee_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entity_type', 50); // 'fee_assignment','transaction','allocation'
            $table->string('entity_id', 36);   // UUID of the entity
            $table->string('action', 50);      // 'created','confirmed','rejected','adjusted'
            $table->json('old_values')->nullable();
            $table->json('new_values');
            $table->foreignUuid('performed_by')->constrained('users');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index('created_at');
        });

        // ============================================
        // 8. NOTIFICATION PREFERENCES - Per-student settings
        // ============================================
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->boolean('sms_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->boolean('due_reminder')->default(true);
            $table->boolean('overdue_alert')->default(true);
            $table->boolean('payment_confirmation')->default(true);
            $table->boolean('payment_rejection')->default(true);
            $table->boolean('installment_reminder')->default(true);
            $table->string('sms_phone', 20)->nullable();
            $table->string('email_address', 100)->nullable();
            $table->timestamps();

            $table->unique('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('fee_audit_logs');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('student_fee_assignments');
        Schema::dropIfExists('installment_plans');
        Schema::dropIfExists('late_fee_rules');
        Schema::dropIfExists('discount_rules');
    }
};
