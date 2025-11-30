<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('post_comment_reply_reactions', function (Blueprint $table) {

            $table->id();
            // Foreign keys
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade'); // User who reacted
            $table->string('platform_reply_id')->nullable();
            // Reaction type
            $table->enum('type', ['like', 'love', 'care', 'haha', 'wow', 'sad', 'angry'])->default('like');
            // Optional: allow users to change reactions later
            $table->timestamps();
            // Soft delete for removed reactions
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_comment_reply_reactions');
    }
};
