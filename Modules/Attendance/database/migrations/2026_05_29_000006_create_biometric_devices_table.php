<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('biometric_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('device_name');
            $table->string('device_type', 50)->default('zkteco');
            $table->string('ip_address', 45)->nullable();
            $table->integer('port')->nullable();
            $table->string('serial_no', 100)->nullable();
            $table->string('driver', 50)->default('fake');
            $table->text('config')->nullable();
            $table->enum('status', ['online', 'offline', 'error'])->default('offline');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('biometric_devices');
    }
};
