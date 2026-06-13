<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds 'active' to the biometric_devices.status ENUM to prevent
     * "Data truncated for column 'status'" errors.
     */
    public function up(): void
    {
        // MySQL does not support adding values to ENUM directly via Schema builder.
        // We use a raw statement to modify the ENUM column.
        DB::statement("ALTER TABLE biometric_devices MODIFY COLUMN status ENUM('online', 'offline', 'error', 'active') NOT NULL DEFAULT 'offline'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE biometric_devices MODIFY COLUMN status ENUM('online', 'offline', 'error') NOT NULL DEFAULT 'offline'");
    }
};
