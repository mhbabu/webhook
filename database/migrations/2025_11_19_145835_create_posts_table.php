<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained();
            $table->foreignId('platform_account_id')->constrained('platform_accounts');
            $table->string('platform_post_id')->index(); // remote id
            $table->string('type')->nullable(); // text, image, video
            $table->text('caption')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->json('raw')->nullable(); // full API payload
            $table->timestamps();

            $table->unique(['platform_id', 'platform_post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
