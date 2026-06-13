<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('exams', function (Blueprint $table) {
            // Add missing batch_id and course_id columns
            if (!Schema::hasColumn('exams', 'batch_id')) {
                $table->foreignUuid('batch_id')->nullable()->constrained('batches')->cascadeOnDelete();
            }
            if (!Schema::hasColumn('exams', 'course_id')) {
                $table->foreignUuid('course_id')->nullable()->constrained('courses')->cascadeOnDelete();
            }

            // Make class_id and section_id nullable (they were non-nullable in original migration)
            $table->foreignUuid('class_id')->nullable()->change();
            $table->foreignUuid('section_id')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn(['batch_id', 'course_id']);
            $table->foreignUuid('class_id')->change();
            $table->foreignUuid('section_id')->change();
        });
    }
};
