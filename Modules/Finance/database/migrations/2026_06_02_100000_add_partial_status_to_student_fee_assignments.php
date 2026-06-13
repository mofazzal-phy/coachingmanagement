<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE student_fee_assignments MODIFY COLUMN status ENUM('pending', 'partial', 'paid', 'overdue', 'waived') NOT NULL DEFAULT 'pending'"
        );
    }

    public function down(): void
    {
        DB::table('student_fee_assignments')
            ->where('status', 'partial')
            ->update(['status' => 'pending']);

        DB::statement(
            "ALTER TABLE student_fee_assignments MODIFY COLUMN status ENUM('pending', 'paid', 'overdue', 'waived') NOT NULL DEFAULT 'pending'"
        );
    }
};
