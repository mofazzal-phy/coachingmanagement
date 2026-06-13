<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_fee_payments', function (Blueprint $table) {
            // Payment status workflow
            if (!Schema::hasColumn('monthly_fee_payments', 'payment_status')) {
                $table->enum('payment_status', ['pending', 'awaiting_confirmation', 'confirmed', 'rejected'])
                    ->default('pending')
                    ->after('note');
            }

            // Confirmation fields
            if (!Schema::hasColumn('monthly_fee_payments', 'confirmed_by')) {
                $table->uuid('confirmed_by')->nullable()->after('payment_status');
                $table->foreign('confirmed_by')->references('id')->on('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('monthly_fee_payments', 'confirmed_at')) {
                $table->timestamp('confirmed_at')->nullable()->after('confirmed_by');
            }
            if (!Schema::hasColumn('monthly_fee_payments', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('confirmed_at');
            }

            // Payment method specific fields
            if (!Schema::hasColumn('monthly_fee_payments', 'sender_number')) {
                $table->string('sender_number', 50)->nullable()->after('transaction_id');
            }
            if (!Schema::hasColumn('monthly_fee_payments', 'bank_name')) {
                $table->string('bank_name', 100)->nullable()->after('sender_number');
            }
            if (!Schema::hasColumn('monthly_fee_payments', 'gateway_response')) {
                $table->json('gateway_response')->nullable()->after('bank_name');
            }

            // Invoice reference
            if (!Schema::hasColumn('monthly_fee_payments', 'invoice_no')) {
                $table->string('invoice_no', 50)->nullable()->unique()->after('gateway_response');
            }
        });
    }

    public function down(): void
    {
        Schema::table('monthly_fee_payments', function (Blueprint $table) {
            $columns = [
                'payment_status', 'confirmed_by', 'confirmed_at', 'rejection_reason',
                'sender_number', 'bank_name', 'gateway_response', 'invoice_no',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('monthly_fee_payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
