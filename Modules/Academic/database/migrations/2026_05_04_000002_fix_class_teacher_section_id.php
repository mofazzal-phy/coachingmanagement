<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop foreign keys first, then primary key, modify column, re-add
        Schema::table('class_teacher', function (Blueprint $table) {
            $table->dropForeign(['class_id']);
            $table->dropForeign(['section_id']);
            $table->dropForeign(['teacher_id']);
        });

        DB::statement('ALTER TABLE class_teacher DROP PRIMARY KEY');
        DB::statement('ALTER TABLE class_teacher MODIFY section_id CHAR(36) NULL');
        DB::statement('ALTER TABLE class_teacher ADD PRIMARY KEY (class_id, teacher_id)');

        Schema::table('class_teacher', function (Blueprint $table) {
            $table->foreign('class_id')->references('id')->on('classes')->cascadeOnDelete();
            $table->foreign('section_id')->references('id')->on('sections')->nullOnDelete();
            $table->foreign('teacher_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('class_teacher', function (Blueprint $table) {
            $table->dropForeign(['class_id']);
            $table->dropForeign(['section_id']);
            $table->dropForeign(['teacher_id']);
        });

        DB::statement('ALTER TABLE class_teacher DROP PRIMARY KEY');
        DB::statement('ALTER TABLE class_teacher MODIFY section_id CHAR(36) NOT NULL');
        DB::statement('ALTER TABLE class_teacher ADD PRIMARY KEY (class_id, section_id, teacher_id)');

        Schema::table('class_teacher', function (Blueprint $table) {
            $table->foreign('class_id')->references('id')->on('classes')->cascadeOnDelete();
            $table->foreign('section_id')->references('id')->on('sections')->cascadeOnDelete();
            $table->foreign('teacher_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
