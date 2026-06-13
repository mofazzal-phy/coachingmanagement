<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_fee_records', function (Blueprint $table) {
            if (!Schema::hasColumn('monthly_fee_records', 'fine_amount')) {
                $table->decimal('fine_amount', 10, 2)->default(0)->after('due_amount');
            }
            if (!Schema::hasColumn('monthly_fee_records', 'remarks')) {
                $table->string('remarks', 255)->nullable()->after('fine_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('monthly_fee_records', function (Blueprint $table) {
            foreach (['fine_amount', 'remarks'] as $col) {
                if (Schema::hasColumn('monthly_fee_records', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
