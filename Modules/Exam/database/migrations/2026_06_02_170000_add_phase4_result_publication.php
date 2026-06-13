<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            if (!Schema::hasColumn('exams', 'result_status')) {
                $table->string('result_status', 20)->default('draft')->after('delivery_mode');
            }
            if (!Schema::hasColumn('exams', 'result_publish_at')) {
                $table->timestamp('result_publish_at')->nullable()->after('result_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            if (Schema::hasColumn('exams', 'result_publish_at')) {
                $table->dropColumn('result_publish_at');
            }
            if (Schema::hasColumn('exams', 'result_status')) {
                $table->dropColumn('result_status');
            }
        });
    }
};
