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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('profile_photo')->nullable();
            $table->string('platform_user_id')->unique()->nullable();
            $table->foreignId('platform_id')->constrained()->onDelete('cascade'); // platform of this customer
            $table->tinyInteger('is_verified')->default(1); // 1 = verified, 0 = not verified
            $table->tinyInteger('is_requested')->default(0); // 1 = requested, 0 = not requested
            $table->string('token')->nullable();
            $table->dateTime('token_expires_at')->nullable();
            $table->timestamps();

            $table->index('platform_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
