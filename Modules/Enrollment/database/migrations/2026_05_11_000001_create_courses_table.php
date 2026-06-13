<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('code')->unique();

            // Category: academic or admission_coaching
            $table->enum('category', ['academic', 'admission_coaching']);

            // For academic: reference class (uuid) & group (bigint)
            $table->uuid('class_id')->nullable();
            $table->unsignedBigInteger('group_id')->nullable();

            // For admission coaching: target university/exam type
            $table->string('target')->nullable();

            // Mode support
            $table->boolean('has_online')->default(true);
            $table->boolean('has_offline')->default(true);

            // Duration
            $table->integer('duration_days')->nullable();
            $table->string('duration_label')->nullable();

            // Details
            $table->text('description')->nullable();
            $table->string('short_description')->nullable();
            $table->string('cover_image')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('class_id')->references('id')->on('classes')->onDelete('set null');
            $table->foreign('group_id')->references('id')->on('academic_groups')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
