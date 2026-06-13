<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('admissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('admission_no')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->date('date_of_birth');
            $table->enum('gender', ['male', 'female', 'other']);
            $table->string('email')->nullable();
            $table->string('phone');
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip_code')->nullable();
            $table->string('country')->nullable();
            $table->foreignUuid('applying_class_id')->constrained('classes');
            $table->foreignUuid('applying_session_id')->constrained('academic_sessions');
            $table->string('previous_school')->nullable();
            $table->string('previous_class')->nullable();
            $table->string('father_name');
            $table->string('father_phone');
            $table->string('father_occupation')->nullable();
            $table->string('mother_name');
            $table->string('mother_phone');
            $table->string('mother_occupation')->nullable();
            $table->text('documents')->nullable();
            $table->text('remarks')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'waitlisted', 'enrolled'])->default('pending');
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('admissions');
    }
};
