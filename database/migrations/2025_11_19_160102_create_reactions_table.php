<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('comment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform_reaction_id')->nullable(); // if available
            $table->string('user_platform_id')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers');
            $table->string('reaction_type')->nullable(); // like, love, haha...
            $table->timestamp('reacted_at')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reactions');
    }
};
