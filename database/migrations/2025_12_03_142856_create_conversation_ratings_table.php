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
        Schema::create('conversation_ratings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id'); 
            $table->string('platform')->nullable();          // 'whatsapp', 'facebook', etc.
            $table->string('option_label');                 // e.g., 'Good', 'Excellent'
            $table->tinyInteger('rating_value');          
            $table->string('interactive_type')->nullable(); 
            $table->text('comments')->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_ratings');
    }
};
