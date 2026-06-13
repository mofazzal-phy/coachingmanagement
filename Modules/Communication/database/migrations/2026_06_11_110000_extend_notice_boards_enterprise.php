<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notice_boards', function (Blueprint $table) {
            $table->string('audience')->default('all')->after('content');
            $table->json('audience_ids')->nullable()->after('audience');
            $table->boolean('is_featured')->default(false)->after('status');
            $table->unsignedInteger('featured_order')->default(0)->after('is_featured');
            $table->timestamp('scheduled_at')->nullable()->after('expiry_date');
            $table->timestamp('published_at')->nullable()->after('scheduled_at');
            $table->string('seo_meta_title')->nullable()->after('published_at');
            $table->text('seo_meta_description')->nullable()->after('seo_meta_title');
            $table->string('approval_status', 20)->nullable()->after('seo_meta_description');
            $table->foreignUuid('approved_by')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('rejection_reason')->nullable()->after('approved_at');
            $table->unsignedBigInteger('view_count')->default(0)->after('rejection_reason');
            $table->unsignedBigInteger('download_count')->default(0)->after('view_count');
            $table->foreignUuid('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('notice_boards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'audience',
                'audience_ids',
                'is_featured',
                'featured_order',
                'scheduled_at',
                'published_at',
                'seo_meta_title',
                'seo_meta_description',
                'approval_status',
                'approved_at',
                'rejection_reason',
                'view_count',
                'download_count',
            ]);
        });
    }
};
