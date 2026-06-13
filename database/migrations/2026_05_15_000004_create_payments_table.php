<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('enrollment_id');
            $table->string('receipt_no', 30)->unique();
            $table->string('payment_method', 50); // cash, bkash, nagad, rocket, bank, card
            $table->decimal('amount', 10, 2);
            $table->decimal('received_amount', 10, 2)->default(0);
            $table->string('transaction_id', 100)->nullable();
            $table->string('reference', 100)->nullable();
            $table->datetime('payment_date')->nullable();
            $table->text('payment_note')->nullable();
            $table->string('payment_status', 30)->default('pending'); // pending, verification_pending, paid, failed, refunded
            $table->uuid('verified_by')->nullable();
            $table->datetime('verified_at')->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamps();

            $table->foreign('enrollment_id')->references('id')->on('enrollments')->onDelete('cascade');
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
