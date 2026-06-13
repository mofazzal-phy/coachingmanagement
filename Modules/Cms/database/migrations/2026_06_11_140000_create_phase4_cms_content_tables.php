<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('success_stories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('story');
            $table->string('student_name')->nullable();
            $table->string('exam_name')->nullable();
            $table->string('achievement_year', 20)->nullable();
            $table->string('result_summary')->nullable();
            $table->string('featured_image')->nullable();
            $table->json('gallery_images')->nullable();
            $table->foreignUuid('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignUuid('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->integer('sort_order')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('inactive');
            $this->addEnterpriseColumns($table);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'sort_order']);
            $table->index('is_featured');
        });

        Schema::create('study_materials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('slug')->nullable()->unique();
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->string('media_type', 20)->default('pdf'); // pdf, video, image, document
            $table->foreignUuid('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignUuid('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignUuid('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignUuid('academic_session_id')->nullable()->constrained('academic_sessions')->nullOnDelete();
            $table->string('access_level', 20)->default('student'); // public, student, teacher, staff
            $table->integer('sort_order')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('inactive');
            $this->addEnterpriseColumns($table);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'access_level']);
            $table->index(['batch_id', 'subject_id']);
        });

        Schema::create('download_resources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('slug')->unique();
            $table->string('category', 30)->default('other'); // brochure, form, syllabus, policy, other
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->string('access_level', 20)->default('authenticated'); // public, authenticated, staff
            $table->integer('sort_order')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('inactive');
            $this->addEnterpriseColumns($table);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'category']);
            $table->index('access_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('download_resources');
        Schema::dropIfExists('study_materials');
        Schema::dropIfExists('success_stories');
    }

    private function addEnterpriseColumns(Blueprint $table): void
    {
        $table->boolean('is_featured')->default(false);
        $table->unsignedInteger('featured_order')->default(0);
        $table->timestamp('published_at')->nullable();
        $table->timestamp('scheduled_at')->nullable();
        $table->timestamp('expires_at')->nullable();
        $table->string('seo_keywords')->nullable();
        $table->string('og_image')->nullable();
        $table->string('canonical_url')->nullable();
        $table->string('approval_status', 20)->nullable();
        $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamp('approved_at')->nullable();
        $table->text('rejection_reason')->nullable();
        $table->unsignedBigInteger('view_count')->default(0);
        $table->unsignedBigInteger('download_count')->default(0);
        $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
    }
};
