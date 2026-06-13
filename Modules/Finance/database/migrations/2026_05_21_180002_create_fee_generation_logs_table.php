<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_generation_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->string('generation_type', 50)->default('auto'); // 'auto', 'manual', 'migration'
            $table->json('summary')->nullable(); // {total_assignments: 5, categories: ["one_time","monthly"]}
            $table->text('notes')->nullable();
            $table->foreignUuid('generated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index('enrollment_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_generation_logs');
    }
};
