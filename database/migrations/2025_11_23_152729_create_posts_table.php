<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();

            // Post content and source
            $table->string('platform_post_id')->nullable(); // facebook_post_id, instagram_post_id, 
            $table->longText('content')->nullable();
            $table->string('source_id')->nullable(); // e.g., Facebook, Instagram, WhatsApp

            // Privacy
            $table->enum('privacy', ['public', 'friends', 'only_me', 'custom'])->default('public');

            // Audit fields (from customers table)
            $table->foreignId('posted_by')->constrained('customers')->onDelete('cascade');
            $table->foreignId('edited_by')->nullable()->constrained('customers')->onDelete('set null');
            $table->foreignId('deleted_by')->nullable()->constrained('customers')->onDelete('set null');

            // Timestamps
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamp('scheduled_at')->nullable(); // for scheduled posts

            // Counts
            $table->integer('comments_count')->default(0);
            $table->integer('shares_count')->default(0);

            // Reactions as JSON
            $table->json('reactions')->nullable(); // e.g., {"total":100,"like":60,"love":20,"care":5,"angry":5,"sad":10,"wow":0}

            // Tags
            $table->json('tags')->nullable(); // tagged friends [{id:123, name:"John"}]
            $table->json('hashtags')->nullable(); // hashtags ["fun","holiday"]

            // Extra info
            $table->string('permalink_url')->nullable();
            $table->string('location')->nullable();
            $table->string('feeling')->nullable();
            $table->string('activity')->nullable();
            $table->string('post_type')->default('status'); // status, photo, video, link

            // Meta
            $table->string('language')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_sponsored')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
