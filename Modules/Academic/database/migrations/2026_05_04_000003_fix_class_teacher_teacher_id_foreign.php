<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop existing foreign key that references users table
        Schema::table('class_teacher', function (Blueprint $table) {
            $table->dropForeign(['teacher_id']);
        });

        // Re-add foreign key referencing teachers table instead of users
        Schema::table('class_teacher', function (Blueprint $table) {
            $table->foreign('teacher_id')->references('id')->on('teachers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('class_teacher', function (Blueprint $table) {
            $table->dropForeign(['teacher_id']);
        });

        Schema::table('class_teacher', function (Blueprint $table) {
            $table->foreign('teacher_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
