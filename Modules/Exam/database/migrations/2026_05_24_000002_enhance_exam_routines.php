<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_routines', function (Blueprint $table) {
            // Level support
            $table->foreignUuid('batch_id')->nullable()->after('subject_id')
                ->constrained('batches')->cascadeOnDelete();
            $table->foreignUuid('course_id')->nullable()->after('batch_id')
                ->constrained('courses')->cascadeOnDelete();
            $table->foreignUuid('class_id')->nullable()->after('course_id')
                ->constrained('classes')->cascadeOnDelete();
            $table->uuid('group_id')->nullable()->after('class_id');

            // Resource assignment
            $table->foreignUuid('room_id')->nullable()->after('end_time')
                ->constrained('rooms')->nullOnDelete();
            $table->foreignUuid('teacher_id')->nullable()->after('room_id')
                ->constrained('teachers')->nullOnDelete();

            // Status & tracking
            $table->enum('status', ['draft', 'published', 'completed', 'cancelled'])
                ->default('draft')->after('pass_marks');
            $table->foreignUuid('created_by')->nullable()->after('status')
                ->constrained('users');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('exam_routines', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropForeign(['course_id']);
            $table->dropForeign(['class_id']);
            $table->dropForeign(['room_id']);
            $table->dropForeign(['teacher_id']);
            $table->dropForeign(['created_by']);

            $table->dropColumn([
                'batch_id', 'course_id', 'class_id', 'group_id',
                'room_id', 'teacher_id', 'status', 'created_by',
                'deleted_at',
            ]);
        });
    }
};
