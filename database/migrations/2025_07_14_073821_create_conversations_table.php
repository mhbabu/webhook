<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('cascade');
            $table->foreignId('agent_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('platform')->nullable();
            $table->string('trace_id')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('end_at')->nullable();
            $table->foreignId('ended_by')->nullable()->constrained('users')->onDelete('set null');
             $table->string('reason')->nullable(); 
            $table->timestamps();

            $table->index(['customer_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
