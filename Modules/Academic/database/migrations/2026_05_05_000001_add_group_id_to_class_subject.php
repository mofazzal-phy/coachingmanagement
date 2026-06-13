<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_subject', function (Blueprint $table) {
            // Add group_id column (nullable - for classes 1-8, group is not needed)
            $table->foreignId('group_id')
                ->nullable()
                ->constrained('academic_groups')
                ->nullOnDelete()
                ->after('subject_id');
        });
    }

    public function down(): void
    {
        Schema::table('class_subject', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropColumn('group_id');
        });
    }
};
