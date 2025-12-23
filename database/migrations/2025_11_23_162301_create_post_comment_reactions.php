<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('comments_reactions', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->foreignId('post_id')->constrained()->onDelete('cascade');   // Post being reacted to
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade'); // User who reacted

            // Reaction type
            $table->enum('type', ['like', 'love', 'care', 'haha', 'wow', 'sad', 'angry'])->default('like');
            // Optional: allow users to change reactions later
            $table->timestamps();
            // Soft delete for removed reactions
            $table->softDeletes();

            // Ensure each user can react only once per post
            $table->unique(['post_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_reactions');
    }
};
