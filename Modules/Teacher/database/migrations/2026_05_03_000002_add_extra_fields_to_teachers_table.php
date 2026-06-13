<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            // Teacher type
            $table->enum('teacher_type', ['permanent', 'contracted', 'guest'])->default('permanent')->after('status');
            
            // Academic group (Science, Arts, Commerce, etc.)
            $table->foreignId('group_id')->nullable()->constrained('academic_groups')->nullOnDelete()->after('teacher_type');
            
            // Experience & previous institution
            $table->integer('experience_years')->default(0)->after('group_id');
            $table->string('previous_institution')->nullable()->after('experience_years');
            
            // Salary configuration
            $table->enum('salary_type', ['monthly', 'class_wise', 'subject_wise'])->default('monthly')->after('previous_institution');
            $table->decimal('salary_amount', 12, 2)->default(0)->after('salary_type');
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropColumn(['teacher_type', 'group_id', 'experience_years', 'previous_institution', 'salary_type', 'salary_amount']);
        });
    }
};
