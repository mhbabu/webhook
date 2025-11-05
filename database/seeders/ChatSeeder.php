<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Customer;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;

class ChatSeeder extends Seeder
{
    public function run(): void
    {
        // Create 5 agents and 5 customers
        $agents = User::all();
        $customers = Customer::factory(50)->create();

        // Create 3 conversations
        $conversations = Conversation::factory(50)->make()->each(function ($conversation) use ($agents, $customers) {
            $conversation->agent_id = $agents->random()->id;
            $conversation->customer_id = $customers->random()->id;
            $conversation->save();
        });

        // Total of 10 messages randomly distributed across conversations
        $totalMessages = 500;
        $allMessages = collect();

        for ($i = 0; $i < $totalMessages; $i++) {
            $conversation = $conversations->random();

            $agent = $conversation->agent;
            $customer = $conversation->customer;

            $isAgentSender = rand(0, 1) === 1;

            $sender = $isAgentSender ? $agent : $customer;
            $receiver = $isAgentSender ? $customer : $agent;

            $message = Message::factory()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'sender_type' => get_class($sender),
                'receiver_id' => $receiver->id,
                'receiver_type' => get_class($receiver),
                'direction' => $isAgentSender ? 'outgoing' : 'incoming',
            ]);

            $allMessages->push($message);

            // Optionally attach fake attachments
            MessageAttachment::factory()->count(rand(0, 2))->create([
                'message_id' => $message->id,
            ]);

            // Update conversation's last message
            $conversation->last_message_id = $message->id;
            $conversation->save();
        }
    }
}
