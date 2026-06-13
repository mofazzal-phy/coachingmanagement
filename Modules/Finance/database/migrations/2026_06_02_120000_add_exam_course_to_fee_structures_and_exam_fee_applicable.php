<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_structures', function (Blueprint $table) {
            $table->index('academic_session_id', 'fee_structures_session_idx');
            $table->index('class_id', 'fee_structures_class_idx');
            $table->dropUnique('fee_structure_unique');
            $table->foreignUuid('exam_id')->nullable()->after('fee_type_id')->constrained('exams')->nullOnDelete();
            $table->foreignUuid('course_id')->nullable()->after('exam_id')->constrained('courses')->nullOnDelete();
            $table->unique(
                ['academic_session_id', 'class_id', 'fee_type_id', 'exam_id', 'course_id'],
                'fee_structure_scope_unique'
            );
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->boolean('exam_fee_applicable')->default(false)->after('min_attendance_percent');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn('exam_fee_applicable');
        });

        Schema::table('fee_structures', function (Blueprint $table) {
            $table->dropUnique('fee_structure_scope_unique');
            $table->dropConstrainedForeignId('course_id');
            $table->dropConstrainedForeignId('exam_id');
            $table->unique(['academic_session_id', 'class_id', 'fee_type_id'], 'fee_structure_unique');
            $table->dropIndex('fee_structures_session_idx');
            $table->dropIndex('fee_structures_class_idx');
        });
    }
};
