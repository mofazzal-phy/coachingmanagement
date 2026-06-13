<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entity_type', 80);
            $table->uuid('entity_id');
            $table->string('action', 50);
            $table->text('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->foreignUuid('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index('action');
            $table->index('created_at');
        });

        Schema::create('cms_content_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('content_type', 80);
            $table->uuid('content_id');
            $table->string('event_type', 20); // view, download
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['content_type', 'content_id', 'event_type']);
            $table->index('created_at');
        });

        $this->addEnterpriseColumns('pages');
        $this->addEnterpriseColumns('events');
        $this->addEnterpriseColumns('galleries');
        $this->addEnterpriseColumns('testimonials');
        $this->addEnterpriseColumns('sliders');

        Schema::table('pages', function (Blueprint $table) {
            $table->string('content_type', 20)->default('page')->after('slug');
            $table->text('excerpt')->nullable()->after('content');
            $table->string('featured_image')->nullable()->after('excerpt');
            $table->foreignUuid('author_id')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        Schema::table('galleries', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('testimonials', function (Blueprint $table) {
            $table->softDeletes();
            $table->integer('sort_order')->default(0)->after('rating');
        });
    }

    public function down(): void
    {
        $this->dropEnterpriseColumns('sliders');
        $this->dropEnterpriseColumns('testimonials');
        $this->dropEnterpriseColumns('galleries');
        $this->dropEnterpriseColumns('events');
        $this->dropEnterpriseColumns('pages');

        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('sort_order');
        });

        Schema::table('galleries', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('author_id');
            $table->dropColumn(['content_type', 'excerpt', 'featured_image']);
        });

        Schema::dropIfExists('cms_content_events');
        Schema::dropIfExists('cms_audit_logs');
    }

    private function addEnterpriseColumns(string $tableName): void
    {
        Schema::table($tableName, function (Blueprint $table) {
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
        });
    }

    private function dropEnterpriseColumns(string $tableName): void
    {
        Schema::table($tableName, function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'is_featured',
                'featured_order',
                'published_at',
                'scheduled_at',
                'expires_at',
                'seo_keywords',
                'og_image',
                'canonical_url',
                'approval_status',
                'approved_at',
                'rejection_reason',
                'view_count',
                'download_count',
            ]);
        });
    }
};
