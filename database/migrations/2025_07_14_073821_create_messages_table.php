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

            // Create column first (NO FK here)
            $table->unsignedBigInteger('conversation_id');

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
            $table->timestamp('received_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->string('read_by')->nullable();
            $table->string('platform_message_id')->unique()->nullable();
            $table->string('remarks')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();
        });

        // Add the actual foreign key separately
        Schema::table('messages', function (Blueprint $table) {
            $table->foreign('conversation_id')
                ->references('id')
                ->on('conversations')
                ->onDelete('cascade');
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
