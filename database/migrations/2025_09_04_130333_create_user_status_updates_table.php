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
        Schema::create('user_status_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // User ID (Foreign Key)
            $table->string('status')->nullable();
            // Break request fields
            $table->string('break_request_status')->nullable(); // Status of break request
            $table->string('reason')->nullable(); // Reason for status update
            $table->timestamp('request_at')->nullable(); // Timestamp when break request was made
            $table->timestamp('approved_at')->nullable(); // Timestamp when break was approved
            $table->foreignId('approved_by')->nullable()->constrained('users'); // User who approved (admin)
            // General fields
            $table->timestamp('changed_at')->useCurrent(); // When status was changed
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_status_updates');
    }
};
