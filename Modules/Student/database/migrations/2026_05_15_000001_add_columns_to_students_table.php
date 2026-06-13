<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'current_class_id')) {
                $table->uuid('current_class_id')->nullable()->after('status');
                $table->foreign('current_class_id')->references('id')->on('classes')->onDelete('set null');
            }
            if (!Schema::hasColumn('students', 'current_section_id')) {
                $table->uuid('current_section_id')->nullable()->after('current_class_id');
                $table->foreign('current_section_id')->references('id')->on('sections')->onDelete('set null');
            }
            if (!Schema::hasColumn('students', 'academic_session_id')) {
                $table->uuid('academic_session_id')->nullable()->after('current_section_id');
                $table->foreign('academic_session_id')->references('id')->on('academic_sessions')->onDelete('set null');
            }
            if (!Schema::hasColumn('students', 'roll_no')) {
                $table->string('roll_no', 20)->nullable()->after('academic_session_id');
            }
            if (!Schema::hasColumn('students', 'group_id')) {
                $table->unsignedBigInteger('group_id')->nullable()->after('roll_no');
                $table->foreign('group_id')->references('id')->on('academic_groups')->onDelete('set null');
            }
            if (!Schema::hasColumn('students', 'previous_school')) {
                $table->string('previous_school', 255)->nullable()->after('group_id');
            }
            if (!Schema::hasColumn('students', 'previous_class')) {
                $table->string('previous_class', 100)->nullable()->after('previous_school');
            }
            if (!Schema::hasColumn('students', 'ssc_result')) {
                $table->decimal('ssc_result', 5, 2)->nullable()->after('previous_class');
            }
            if (!Schema::hasColumn('students', 'emergency_contact')) {
                $table->string('emergency_contact', 255)->nullable()->after('ssc_result');
            }
            if (!Schema::hasColumn('students', 'emergency_phone')) {
                $table->string('emergency_phone', 20)->nullable()->after('emergency_contact');
            }
            if (!Schema::hasColumn('students', 'remarks')) {
                $table->text('remarks')->nullable()->after('emergency_phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $columns = ['current_class_id','current_section_id','academic_session_id','roll_no','group_id','previous_school','previous_class','ssc_result','emergency_contact','emergency_phone','remarks'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('students', $col)) $table->dropColumn($col);
            }
        });
    }
};
