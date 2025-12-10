<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('platform_comment_id')->index();
            $table->string('platform_parent_id')->nullable()->index(); // for replies
            $table->string('author_platform_id')->nullable()->index(); // remote user id
            $table->foreignId('customer_id')->nullable()->constrained('customers'); // maps to Customer model
            $table->string('author_name')->nullable();
            $table->string('path')->nullable()->index();
            $table->string('type')->nullable(); // comment or reply
            $table->text('message')->nullable();
            $table->timestamp('commented_at')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['post_id', 'platform_comment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
