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
        Schema::create('reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // author
            $table->unsignedBigInteger('reactable_id'); // polymorphic id
            $table->longText('platform_reaction_id')->nullable();
            $table->string('reactable_type'); // polymorphic type: Post, Comment, Reply
            $table->string('reaction_type', 50); // like, love, haha, etc.
            $table->timestamps();

            $table->index(['reactable_id', 'reactable_type']); // improve query
            $table->unique(['user_id', 'reactable_id', 'reactable_type'], 'unique_user_reaction'); // only one reaction per user per item
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reactions');
    }
};
