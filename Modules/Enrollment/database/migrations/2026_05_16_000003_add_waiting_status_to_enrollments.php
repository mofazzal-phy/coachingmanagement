<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add 'waiting' to the enrollment status enum
        DB::statement("ALTER TABLE enrollments MODIFY status ENUM('pending','active','completed','dropped','waiting') DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE enrollments MODIFY status ENUM('pending','active','completed','dropped') DEFAULT 'pending'");
    }
};
