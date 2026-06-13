<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name'); // e.g., "Mathematics", "English"
            $table->string('code')->unique(); // e.g., "SUB-MATH"
            $table->enum('type', ['core', 'elective', 'optional'])->default('core');
            $table->integer('credit_hours')->default(0);
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        // Pivot: class_subject
        Schema::create('class_subject', function (Blueprint $table) {
            $table->foreignUuid('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignUuid('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->integer('total_marks')->default(100);
            $table->integer('pass_marks')->default(33);
            $table->primary(['class_id', 'subject_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('class_subject');
        Schema::dropIfExists('subjects');
    }
};
