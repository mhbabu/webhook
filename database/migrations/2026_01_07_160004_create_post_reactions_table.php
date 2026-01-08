<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('post_reactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('platform_user_id');
            $table->string('type'); // LIKE, LOVE, CARE, WOW, etc.
            $table->timestamps();

            $table->unique(['post_id','platform_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_reactions');
    }
};
