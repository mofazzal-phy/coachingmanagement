<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->string('name');
            $table->string('code')->unique();
            $table->uuid('academic_session_id')->nullable();

            // Mode: online, offline, hybrid
            $table->enum('mode', ['online', 'offline', 'hybrid']);

            // For offline
            $table->uuid('room_id')->nullable();
            $table->string('campus_location')->nullable();

            // For online
            $table->string('platform')->nullable();
            $table->string('meeting_link')->nullable();
            $table->boolean('recording_available')->default(true);

            // Schedule
            $table->json('days')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            // Capacity
            $table->integer('capacity')->default(0);
            $table->integer('enrolled_count')->default(0);
            $table->enum('status', ['open', 'closed', 'full', 'upcoming'])->default('upcoming');

            // Teacher (optional override)
            $table->uuid('teacher_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('academic_session_id')->references('id')->on('academic_sessions')->onDelete('set null');
            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('set null');
            $table->foreign('teacher_id')->references('id')->on('teachers')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
