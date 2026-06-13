<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'present_address')) {
                $table->string('present_address', 500)->nullable()->after('address');
            }
            if (!Schema::hasColumn('students', 'permanent_address')) {
                $table->string('permanent_address', 500)->nullable()->after('present_address');
            }
            if (!Schema::hasColumn('students', 'group_id')) {
                $table->unsignedBigInteger('group_id')->nullable()->after('current_section_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            foreach (['present_address', 'permanent_address', 'group_id'] as $c) {
                if (Schema::hasColumn('students', $c)) $table->dropColumn($c);
            }
        });
    }
};
