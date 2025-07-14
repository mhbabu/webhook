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
        Schema::create('agent_platform_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('platform_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['OCCUPIED', 'BUSY', 'OFFLINE', 'BREAK'])->default('OFFLINE');
            $table->integer('current_load')->default(0);
            $table->integer('max_limit')->default(3);
            $table->timestamps();

            $table->unique(['agent_id', 'platform_id']);
            $table->index(['platform_id', 'status', 'current_load']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_platform_roles');
    }
};
