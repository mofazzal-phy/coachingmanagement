<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'father_name')) {
                $table->string('father_name', 255)->nullable()->after('country');
            }
            if (!Schema::hasColumn('students', 'father_phone')) {
                $table->string('father_phone', 20)->nullable()->after('father_name');
            }
            if (!Schema::hasColumn('students', 'father_occupation')) {
                $table->string('father_occupation', 255)->nullable()->after('father_phone');
            }
            if (!Schema::hasColumn('students', 'mother_name')) {
                $table->string('mother_name', 255)->nullable()->after('father_occupation');
            }
            if (!Schema::hasColumn('students', 'mother_phone')) {
                $table->string('mother_phone', 20)->nullable()->after('mother_name');
            }
            if (!Schema::hasColumn('students', 'mother_occupation')) {
                $table->string('mother_occupation', 255)->nullable()->after('mother_phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $columns = ['father_name', 'father_phone', 'father_occupation', 'mother_name', 'mother_phone', 'mother_occupation'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('students', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
