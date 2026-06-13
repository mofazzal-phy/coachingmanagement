<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('employee_attendances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('attendance_log_id');
            $table->uuid('employee_id');
            $table->uuid('department_id')->nullable();
            $table->uuid('shift_id')->nullable();
            $table->timestamps();

            $table->foreign('attendance_log_id')->references('id')->on('attendance_logs')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->index(['employee_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('employee_attendances');
    }
};
