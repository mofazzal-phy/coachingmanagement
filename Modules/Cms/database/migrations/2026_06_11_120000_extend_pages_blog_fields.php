<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->json('tags')->nullable()->after('featured_image');
            $table->unsignedSmallInteger('reading_time')->nullable()->after('tags');
            $table->unique(['content_type', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropUnique(['content_type', 'slug']);
            $table->dropColumn(['tags', 'reading_time']);
            $table->unique('slug');
        });
    }
};
