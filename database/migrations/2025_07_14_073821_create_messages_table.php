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

            $table->string('type')->default('text');
            $table->string('content')->nullable();
            $table->enum('direction', ['incoming', 'outgoing']);
            $table->timestamp('read_at')->nullable();
            $table->string('read_by')->nullable();
           $table->string('platform_message_id')->unique()->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('last_message_id')->after('end_at')->nullable()->constrained('messages');
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
