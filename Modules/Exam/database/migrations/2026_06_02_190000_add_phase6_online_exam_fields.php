<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_results', function (Blueprint $table) {
            if (!Schema::hasColumn('exam_results', 'exam_attempt_id')) {
                $table->uuid('exam_attempt_id')->nullable()->after('student_id');
                $table->foreign('exam_attempt_id')->references('id')->on('exam_attempts')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('exam_results', function (Blueprint $table) {
            if (Schema::hasColumn('exam_results', 'exam_attempt_id')) {
                $table->dropForeign(['exam_attempt_id']);
                $table->dropColumn('exam_attempt_id');
            }
        });
    }
};
