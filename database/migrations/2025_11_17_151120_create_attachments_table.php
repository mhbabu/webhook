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
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attachable_id'); // polymorphic id
            $table->string('attachable_type'); // polymorphic type: Post, Comment, Reply
            $table->string('file_url'); // path to file
            $table->string('file_type', 50); // image, video, audio, file
            $table->longText('platform_attachment_id')->null();
            $table->string('mime_type')->nullable(); // e.g., image/jpeg
            $table->unsignedBigInteger('file_size')->nullable(); // bytes
            $table->timestamps();

            $table->index(['attachable_id', 'attachable_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
