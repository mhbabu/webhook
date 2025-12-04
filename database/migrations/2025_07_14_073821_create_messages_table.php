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
        Schema::create('messages', function (Blueprint $table) {

            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->onDelete('cascade');

            // Polymorphic sender
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->string('sender_type')->nullable();
            // Optional receiver
            $table->unsignedBigInteger('receiver_id')->nullable();
            $table->string('receiver_type')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->longText('cc_email')->nullable();
            $table->string('type')->nullable();
            $table->string('subject')->nullable();
            $table->longText('content')->nullable();
            $table->enum('direction', ['incoming', 'outgoing']);
            $table->timestamp('read_at')->nullable();
            $table->string('read_by')->nullable();
            $table->string('platform_message_id')->unique()->nullable();
            $table->string('remarks')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
