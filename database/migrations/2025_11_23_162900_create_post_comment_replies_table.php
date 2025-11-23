<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('post_comment_replies', function (Blueprint $table) {
            $table->id();

            // Post reference
            $table->foreignId('comment_id')->constrained()->cascadeOnDelete();
            $table->string('platform_reply_id')->nullable();

            // Customer / Facebook user ID
            $table->unsignedBigInteger('customer_id')->nullable();

            // Comment text
            $table->text('content')->nullable();

            // Single attachment type: image, gif, video, document
            $table->string('attachment_type')->nullable();

            // Single attachment path
            $table->string('attachment_path')->nullable();

            // Mentions as JSON
            $table->json('mentions')->nullable(); // [{"id":"123","name":"John Doe","type":"

            // Timestamps
            $table->timestamp('replied_at')->useCurrent();
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('deleted_at')->nullable();

            // Track who updated/deleted
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_comment_replies');
    }
};
