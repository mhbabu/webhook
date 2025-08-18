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
        Schema::create('role_hierarchy', function (Blueprint $table) {
             $table->id();
            $table->foreignId('parent_role_id')->constrained('roles')->onDelete('cascade'); // Parent role
            $table->foreignId('child_role_id')->constrained('roles')->onDelete('cascade');  // Child role
            $table->timestamps();
            $table->unique(['parent_role_id', 'child_role_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_hierarchy');
    }
};
