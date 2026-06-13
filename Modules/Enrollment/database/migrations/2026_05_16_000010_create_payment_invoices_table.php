<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('monthly_fee_payment_id');
            $table->string('invoice_no', 50)->unique();
            $table->enum('invoice_type', ['monthly_fee', 'one_time', 'installment'])->default('monthly_fee');
            $table->timestamp('generated_at')->useCurrent();
            $table->uuid('generated_by')->nullable();
            $table->string('file_path', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('monthly_fee_payment_id')->references('id')->on('monthly_fee_payments')->onDelete('cascade');
            $table->foreign('generated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_invoices');
    }
};
