<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            if (!Schema::hasColumn('enrollments', 'payment_method')) {
                $table->string('payment_method', 50)->nullable()->after('due_amount');
            }
            if (!Schema::hasColumn('enrollments', 'payment_reference')) {
                $table->string('payment_reference', 100)->nullable()->after('payment_method');
            }
            if (!Schema::hasColumn('enrollments', 'payment_transaction_id')) {
                $table->string('payment_transaction_id', 100)->nullable()->after('payment_reference');
            }
            if (!Schema::hasColumn('enrollments', 'payment_date')) {
                $table->datetime('payment_date')->nullable()->after('payment_transaction_id');
            }
            if (!Schema::hasColumn('enrollments', 'payment_verified_by')) {
                $table->uuid('payment_verified_by')->nullable()->after('payment_date');
                $table->foreign('payment_verified_by')->references('id')->on('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('enrollments', 'payment_verified_at')) {
                $table->datetime('payment_verified_at')->nullable()->after('payment_verified_by');
            }
            if (!Schema::hasColumn('enrollments', 'invoice_no')) {
                $table->string('invoice_no', 30)->nullable()->after('payment_verified_at');
            }
            if (!Schema::hasColumn('enrollments', 'invoice_generated_at')) {
                $table->datetime('invoice_generated_at')->nullable()->after('invoice_no');
            }
        });

        // Modify enrollment_type enum to add 'import'
        Schema::table('enrollments', function (Blueprint $table) {
            if (Schema::hasColumn('enrollments', 'enrollment_type')) {
                DB::statement("ALTER TABLE enrollments MODIFY enrollment_type ENUM('new','renewal','import') DEFAULT 'new'");
            }
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $cols = ['payment_method','payment_reference','payment_transaction_id','payment_date','payment_verified_by','payment_verified_at','invoice_no','invoice_generated_at'];
            foreach ($cols as $c) {
                if (Schema::hasColumn('enrollments', $c)) $table->dropColumn($c);
            }
        });
        DB::statement("ALTER TABLE enrollments MODIFY enrollment_type ENUM('new','renewal') DEFAULT 'new'");
    }
};
