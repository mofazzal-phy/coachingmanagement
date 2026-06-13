<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('biometric_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('device_id');
            $table->string('biometric_uid', 100)->nullable();
            $table->timestamp('scan_time')->nullable();
            $table->enum('sync_status', ['pending', 'synced', 'failed'])->default('pending');
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->foreign('device_id')->references('id')->on('biometric_devices')->onDelete('cascade');
            $table->index(['device_id', 'sync_status']);
            $table->index(['scan_time']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('biometric_logs');
    }
};
