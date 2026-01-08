<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('post_comment_replies', function (Blueprint $table) {
            $table->id();

            $table->foreignId('post_comment_id')->constrained()->cascadeOnDelete();
            $table->string('platform_reply_id')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->longText('content')->nullable();
            $table->json('attachment')->nullable(); // [{"type":"photo","url":"..."},...]
            $table->json('mentions')->nullable();
            $table->timestamp('replied_at')->useCurrent();
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['platform_reply_id','post_comment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_comment_replies');
    }
};
