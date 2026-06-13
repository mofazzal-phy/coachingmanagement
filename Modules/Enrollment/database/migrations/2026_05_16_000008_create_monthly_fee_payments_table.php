<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_fee_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('monthly_fee_record_id');
            $table->uuid('payment_id')->nullable(); // link to existing payments table
            $table->decimal('amount', 10, 2);
            $table->string('payment_method', 50);
            $table->string('transaction_id', 100)->nullable();
            $table->string('reference', 100)->nullable();
            $table->datetime('payment_date');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('monthly_fee_record_id')->references('id')->on('monthly_fee_records')->onDelete('cascade');
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_fee_payments');
    }
};
