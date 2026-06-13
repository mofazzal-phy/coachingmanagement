<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smart_payment_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payment_transaction_id');
            $table->string('invoice_no', 50)->unique();
            $table->string('invoice_type', 50)->default('payment');
            $table->timestamp('generated_at')->nullable();
            $table->uuid('generated_by')->nullable();
            $table->string('file_path', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('payment_transaction_id')
                ->references('id')
                ->on('payment_transactions')
                ->onDelete('cascade');

            $table->foreign('generated_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index('invoice_no');
            $table->index('payment_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_payment_invoices');
    }
};
