<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_routines', function (Blueprint $table) {
            // Add missing columns that don't exist yet
            if (!Schema::hasColumn('exam_routines', 'room_id')) {
                $table->foreignUuid('room_id')->nullable()->after('end_time')
                    ->constrained('rooms')->nullOnDelete();
            }
            if (!Schema::hasColumn('exam_routines', 'teacher_id')) {
                $table->foreignUuid('teacher_id')->nullable()->after('room_id')
                    ->constrained('teachers')->nullOnDelete();
            }
            if (!Schema::hasColumn('exam_routines', 'status')) {
                $table->enum('status', ['draft', 'published', 'completed', 'cancelled'])
                    ->default('draft')->after('pass_marks');
            }
            if (!Schema::hasColumn('exam_routines', 'created_by')) {
                $table->foreignUuid('created_by')->nullable()->after('status')
                    ->constrained('users');
            }
            if (!Schema::hasColumn('exam_routines', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('exam_routines', function (Blueprint $table) {
            $columns = ['room_id', 'teacher_id', 'status', 'created_by', 'deleted_at'];
            $existing = array_intersect($columns, Schema::getColumnListing('exam_routines'));

            if (in_array('room_id', $existing)) {
                $table->dropForeign(['room_id']);
            }
            if (in_array('teacher_id', $existing)) {
                $table->dropForeign(['teacher_id']);
            }
            if (in_array('created_by', $existing)) {
                $table->dropForeign(['created_by']);
            }

            $table->dropColumn($existing);
        });
    }
};
