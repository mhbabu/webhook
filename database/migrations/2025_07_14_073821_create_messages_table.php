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
            // $table->id();
            // $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            // $table->string('sender_id')->nullable();
            // $table->string('receiver_id')->nullable();
            // $table->string('type')->default('text');
            // $table->text('content')->nullable();
            // $table->enum('direction', ['incoming', 'outgoing']);
            // $table->timestamps();

            $table->id();
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');

            // Sender (polymorphic: User or Customer)
            $table->unsignedBigInteger('sender_id');
            $table->string('sender_type'); // App\Models\User or App\Models\Customer

            // Optional receiver (only for multi-agent/group chat)
            $table->unsignedBigInteger('receiver_id')->nullable();
            $table->string('receiver_type')->nullable(); // App\Models\User or App\Models\Customer

            $table->string('type')->default('text');
            $table->text('content')->nullable();

            $table->enum('direction', ['incoming', 'outgoing']); // incoming = customer → agent
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('last_message_id')->nullable()->constrained('messages');
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
