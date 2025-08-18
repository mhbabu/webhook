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
        Schema::create('message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages')->onDelete('cascade'); // optional, auto-delete attachments if message is deleted
            $table->string('type', 255)->nullable();
            $table->string('path', 1024)->nullable();
            $table->string('mime', 255)->nullable();
            $table->string('size');
            $table->string('attachment_id')->nullable();
            $table->tinyInteger('is_available')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
