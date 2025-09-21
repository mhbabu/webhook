<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Message;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Customer;

class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        $conversation = Conversation::inRandomOrder()->first();

        // Randomly pick sender: agent or customer
        $isAgentSender = $this->faker->boolean;
        $sender = $isAgentSender ? $conversation->agent : $conversation->customer;
        $receiver = $isAgentSender ? $conversation->customer : $conversation->agent;

        return [
            'conversation_id' => $conversation->id,
            'sender_id'       => $sender->id ?? null,
            'sender_type'     => get_class($sender),
            'receiver_id'     => $receiver->id ?? null,
            'receiver_type'   => get_class($receiver),
            'type'            => 'text',
            'content'         => $this->faker->sentence(),
            'direction'       => $isAgentSender ? 'outgoing' : 'incoming',
            'created_at'      => now()->subMinutes(rand(0, 1440)),
        ];
    }
}
