<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('message');
            $table->string('type'); // email, sms, push, in_app
            $table->string('audience'); // all, students, teachers, staff, parents, custom
            $table->json('audience_ids')->nullable();
            $table->foreignUuid('sent_by')->constrained('users');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->enum('status', ['draft', 'scheduled', 'sent', 'failed'])->default('draft');
            $table->timestamps();
        });

        Schema::create('notice_boards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('content');
            $table->string('priority')->default('normal'); // low, normal, high, urgent
            $table->date('publish_date');
            $table->date('expiry_date')->nullable();
            $table->json('attachments')->nullable();
            $table->foreignUuid('created_by')->constrained('users');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sms_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('recipient');
            $table->text('message');
            $table->string('gateway')->nullable();
            $table->string('status'); // sent, failed, pending
            $table->text('response')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sms_logs');
        Schema::dropIfExists('notice_boards');
        Schema::dropIfExists('notifications');
    }
};
