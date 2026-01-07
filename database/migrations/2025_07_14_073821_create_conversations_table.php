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
            $table->bigInteger('last_message_id')->nullable()->constrained('messages')->onDelete('set null');
            $table->bigInteger('post_id')->nullable()->constrained('posts')->onDelete('set null');
            $table->foreignId('type')->nullable();
            $table->string('type_id')->nullable();
            $table->string('platform')->nullable();
            $table->string('trace_id')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('end_at')->nullable();
            $table->foreignId('ended_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('wrap_up_id')->nullable()->constrained('wrap_up_conversations')->onDelete('set null');
            $table->timestamp('in_queue_at')->nullable();
            $table->timestamp('first_message_at')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('agent_assigned_at')->nullable();
            $table->tinyInteger('is_feedback_sent')->default(0);
            $table->timestamps();

            $table->index(['customer_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
