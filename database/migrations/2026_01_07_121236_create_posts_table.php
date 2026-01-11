<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();

            // Platform info
            $table->string('platform_post_id')->nullable(); // e.g., Facebook, Instagram post ID
            $table->string('source')->nullable(); // platform name
            $table->longText('content')->nullable();
            // Privacy
            $table->json('privacy')->nullable();
            // Audit
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            // Timing
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('deleted_at')->nullable();

            // Counts
            $table->integer('comments_count')->default(0);
            $table->integer('shares_count')->default(0);

            // Reactions & tags
            $table->json('attachment')->nullable(); // [{"type":"photo","url":"..."},...]
            $table->json('reactions')->nullable(); // {"like":10,"love":5,...}
            $table->json('tags')->nullable(); // [{"id":123,"name":"John"}]
            $table->json('hashtags')->nullable(); // ["fun","holiday"]

            // Post details
            $table->string('permalink_url')->nullable();
            $table->json('location')->nullable();
            $table->string('feeling')->nullable();
            $table->string('activity')->nullable();
            $table->string('post_type')->default('status'); // status, photo, video, link

            // Meta
            $table->string('language')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_sponsored')->default(false);

            $table->timestamps();

            // $table->unique(['platform_post_id','source']);
            $table->index('posted_at');
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
