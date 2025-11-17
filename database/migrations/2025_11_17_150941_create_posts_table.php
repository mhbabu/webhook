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
        Schema::create('posts', function (Blueprint $table) {
            $table->id(); // bigint PK
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // author
            $table->longText('content')->nullable(); // text content
            $table->string('privacy', 20)->default('public'); // privacy setting
            $table->longText('platform_post_id')->nullable();
            $table->timestamps(); // created_at, updated_at
            $table->softDeletes(); // deleted_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
