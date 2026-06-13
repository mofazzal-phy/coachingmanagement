<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'city')) {
                $table->string('city', 100)->nullable()->after('permanent_address');
            }
            if (!Schema::hasColumn('students', 'state')) {
                $table->string('state', 100)->nullable()->after('city');
            }
            if (!Schema::hasColumn('students', 'zip_code')) {
                $table->string('zip_code', 20)->nullable()->after('state');
            }
            if (!Schema::hasColumn('students', 'country')) {
                $table->string('country', 100)->nullable()->after('zip_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $columns = ['city', 'state', 'zip_code', 'country'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('students', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
