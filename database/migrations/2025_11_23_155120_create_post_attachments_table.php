<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('post_attachments', function (Blueprint $table) {
            $table->id();

            // Foreign key to posts table
            $table->foreignId('post_id')->constrained()->onDelete('cascade');
            $table->string('platform_attachment_id')->nullable();

            // Media info
            $table->enum('type', ['image', 'video', 'link', 'album'])->default('image');
            $table->string('url'); // main media URL
            $table->string('thumbnail_url')->nullable(); // optional thumbnail for video or preview

            // Extra info
            $table->longText('description')->nullable(); // caption
            $table->json('tags')->nullable(); // people tagged [{"id":123,"name":"John"}]
            $table->integer('position')->default(0); // order in the post
            $table->json('metadata')->nullable(); // extra metadata like duration, resolution, size

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_attachments');
    }
};
