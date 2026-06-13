<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_routines', function (Blueprint $table) {
            if (!Schema::hasColumn('exam_routines', 'exam_type_id')) {
                $table->foreignUuid('exam_type_id')->nullable()->after('subject_id')
                    ->constrained('exam_types')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('exam_routines', function (Blueprint $table) {
            if (Schema::hasColumn('exam_routines', 'exam_type_id')) {
                $table->dropForeign(['exam_type_id']);
                $table->dropColumn('exam_type_id');
            }
        });
    }
};
