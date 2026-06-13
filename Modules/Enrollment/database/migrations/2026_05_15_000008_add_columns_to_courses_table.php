<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            if (!Schema::hasColumn('courses', 'level')) {
                $table->string('level', 50)->nullable()->after('category');
            }
            if (!Schema::hasColumn('courses', 'meta_title')) {
                $table->string('meta_title', 255)->nullable()->after('short_description');
            }
            if (!Schema::hasColumn('courses', 'meta_description')) {
                $table->string('meta_description', 500)->nullable()->after('meta_title');
            }
            if (!Schema::hasColumn('courses', 'learning_outcomes')) {
                $table->text('learning_outcomes')->nullable()->after('description');
            }
            if (!Schema::hasColumn('courses', 'syllabus')) {
                $table->text('syllabus')->nullable()->after('learning_outcomes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            foreach (['level','meta_title','meta_description','learning_outcomes','syllabus'] as $c) {
                if (Schema::hasColumn('courses', $c)) $table->dropColumn($c);
            }
        });
    }
};
