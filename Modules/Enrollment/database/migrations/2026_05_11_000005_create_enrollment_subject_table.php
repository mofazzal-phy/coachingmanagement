<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_subject', function (Blueprint $table) {
            $table->uuid('enrollment_id');
            $table->uuid('subject_id');
            $table->decimal('subject_fee', 10, 2)->default(0);
            $table->uuid('teacher_id')->nullable();
            $table->uuid('batch_id')->nullable();
            $table->timestamps();

            $table->primary(['enrollment_id', 'subject_id']);
            $table->foreign('enrollment_id')->references('id')->on('enrollments')->onDelete('cascade');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
            $table->foreign('teacher_id')->references('id')->on('teachers')->onDelete('set null');
            $table->foreign('batch_id')->references('id')->on('batches')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_subject');
    }
};
