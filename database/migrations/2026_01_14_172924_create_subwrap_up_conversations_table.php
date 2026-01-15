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
        Schema::create('subwrap_up_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wrap_up_conversation_id')->constrained('wrap_up_conversations')->cascadeOnDelete();
            $table->string('name', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subwrap_up_conversations');
    }
};
