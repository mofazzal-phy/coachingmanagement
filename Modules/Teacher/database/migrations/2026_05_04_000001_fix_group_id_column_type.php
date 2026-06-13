<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Check if group_id column exists first
        if (Schema::hasColumn('teachers', 'group_id')) {
            Schema::table('teachers', function (Blueprint $table) {
                // Try to drop foreign key if exists, then drop column
                try {
                    $table->dropForeign(['group_id']);
                } catch (\Exception $e) {
                    // Foreign key may not exist
                }
                $table->dropColumn('group_id');
            });
        }

        Schema::table('teachers', function (Blueprint $table) {
            // Re-add with correct type (foreignId for integer PK)
            $table->foreignId('group_id')->nullable()->constrained('academic_groups')->nullOnDelete()->after('teacher_type');
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            try {
                $table->dropForeign(['group_id']);
            } catch (\Exception $e) {}
            $table->dropColumn('group_id');
        });

        Schema::table('teachers', function (Blueprint $table) {
            $table->foreignUuid('group_id')->nullable()->constrained('academic_groups')->nullOnDelete()->after('teacher_type');
        });
    }
};
