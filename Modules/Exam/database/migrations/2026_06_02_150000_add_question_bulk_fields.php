<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->uuid('question_set_id')->nullable()->after('id');
            $table->string('set_title')->nullable()->after('question_set_id');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('set_title');
            $table->text('stimulus')->nullable()->after('content');
            $table->json('parts')->nullable()->after('options');
            $table->index(['question_set_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex(['question_set_id', 'sort_order']);
            $table->dropColumn(['question_set_id', 'set_title', 'sort_order', 'stimulus', 'parts']);
        });
    }
};
