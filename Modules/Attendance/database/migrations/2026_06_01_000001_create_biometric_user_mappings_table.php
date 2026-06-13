<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_user_mappings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('device_id')->nullable();
            $table->string('biometric_uid', 100);
            $table->string('user_type', 50);
            $table->uuid('user_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('device_id')->references('id')->on('biometric_devices')->nullOnDelete();
            $table->unique('biometric_uid');
            $table->index(['user_type', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_user_mappings');
    }
};
