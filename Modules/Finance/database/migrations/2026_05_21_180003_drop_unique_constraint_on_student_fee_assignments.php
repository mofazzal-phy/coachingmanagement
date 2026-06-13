<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // InnoDB requires an index on foreign key columns.
        // The unique constraint `student_fee_unique` on (enrollment_id, fee_structure_id)
        // serves as the index for the enrollment_id FK.
        // We need to drop the FK, drop the unique, add a regular index, then re-add the FK.

        // 1. Drop foreign key on enrollment_id
        Schema::table('student_fee_assignments', function (Blueprint $table) {
            $table->dropForeign('student_fee_assignments_enrollment_id_foreign');
        });

        // 2. Drop the unique constraint that prevents multiple assignments
        //    per enrollment per fee structure (needed for monthly fee generation)
        Schema::table('student_fee_assignments', function (Blueprint $table) {
            $table->dropUnique('student_fee_unique');
        });

        // 3. Add a regular (non-unique) index on enrollment_id for the FK
        Schema::table('student_fee_assignments', function (Blueprint $table) {
            $table->index('enrollment_id', 'student_fee_assignments_enrollment_id_index');
        });

        // 4. Add a composite index for query performance
        Schema::table('student_fee_assignments', function (Blueprint $table) {
            $table->index(['enrollment_id', 'fee_structure_id'], 'student_fee_idx');
        });

        // 5. Re-add the foreign key
        Schema::table('student_fee_assignments', function (Blueprint $table) {
            $table->foreign('enrollment_id', 'student_fee_assignments_enrollment_id_foreign')
                ->references('id')->on('enrollments')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Reverse: drop new indexes and FK, restore unique constraint

        Schema::table('student_fee_assignments', function (Blueprint $table) {
            $table->dropForeign('student_fee_assignments_enrollment_id_foreign');
        });

        Schema::table('student_fee_assignments', function (Blueprint $table) {
            $table->dropIndex('student_fee_idx');
            $table->dropIndex('student_fee_assignments_enrollment_id_index');
        });

        Schema::table('student_fee_assignments', function (Blueprint $table) {
            $table->unique(['enrollment_id', 'fee_structure_id'], 'student_fee_unique');
        });

        Schema::table('student_fee_assignments', function (Blueprint $table) {
            $table->foreign('enrollment_id', 'student_fee_assignments_enrollment_id_foreign')
                ->references('id')->on('enrollments')->cascadeOnDelete();
        });
    }
};
