<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Customer;
use App\Models\Conversation;
use App\Models\Message;

class ChatSeeder extends Seeder
{
    public function run(): void
    {
        // Make sure we have users and customers first
        if (User::count() == 0) {
            User::factory(10)->create(); // create 10 agents
        }

        if (Customer::count() == 0) {
            Customer::factory(20)->create(); // create 20 customers
        }

        Conversation::factory(1000)->create()->each(function ($conversation) {
            $agentId = $conversation->agent_id;
            $customerId = $conversation->customer_id;

            Message::factory(rand(3, 10))->create([
                'conversation_id' => $conversation->id,
                'sender_id' => function () use ($agentId, $customerId) {
                    return rand(0, 1) ? $agentId : $customerId;
                },
                'sender_type' => function ($attrs) use ($agentId) {
                    return $attrs['sender_id'] == $agentId
                        ? 'App\Models\User'
                        : 'App\Models\Customer';
                },
                'receiver_id' => function ($attrs) use ($agentId, $customerId) {
                    return $attrs['sender_id'] == $agentId
                        ? $customerId
                        : $agentId;
                },
                'receiver_type' => function ($attrs) use ($agentId) {
                    return $attrs['sender_id'] == $agentId
                        ? 'App\Models\Customer'
                        : 'App\Models\User';
                },
            ]);
        });
    }
}
