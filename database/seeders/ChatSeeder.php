<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Customer;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\Platform;
use Carbon\Carbon;

class ChatSeeder extends Seeder
{
    public function run(): void
    {
        $agents = User::where('role_id', 4)->get();
        $allowedPlatforms = Platform::whereIn('name', [
            'whatsapp',
            'facebook_messenger',
            'instagram_message'
        ])->get();

        $bdNames = [
            'Mahmudul Hasan','Abdul Karim','Shakil Ahmed','Sabbir Hossain','Fahim Rahman',
            'Riaz Uddin','Tanmoy Islam','Arif Chowdhury','Mehedi Hasan','Ibrahim Khalil',
            'Nasrin Akter','Sharmin Sultana','Shathi Akter','Mim Chowdhury','Jannatul Ferdous',
            'Sumaiya Akter','Nusrat Jahan','Lamia Islam','Rubaida Rahman','Sadia Afrin'
        ];

        $customerMessages = [
            "Hello, how are you?",
            "I need some help.",
            "Ami ekta product er price jante chai.",
            "Delivery kotodin lage?",
            "Order dite chai.",
            "Apnader service ta valo.",
            "Ekta problem hocche.",
            "Can you help me please?",
            "Thanks!",
            "Ami ki ekto details pete pari?"
        ];

        $agentMessages = [
            "Hello! How can I help you today?",
            "Sure, I can assist you.",
            "Can you please share more details?",
            "Your order is being processed.",
            "Let me check for you.",
            "Thanks for reaching out.",
            "Anything else I can help you with?",
            "Please wait a moment.",
            "Your issue is resolved.",
            "Thank you for contacting us!"
        ];

        $customers = collect();
        for ($i = 0; $i < 50; $i++) {
            $platform = $allowedPlatforms->random();

            $customers->push(
                Customer::create([
                    'name' => $bdNames[array_rand($bdNames)],
                    'username' => null,
                    'email' => 'customer' . rand(1000, 9999) . '@example.com',
                    'phone' => $this->generateBangladeshiPhone(),
                    'platform_user_id' => uniqid('user_'),
                    'platform_id' => $platform->id,
                    'profile_photo' => null,
                    'is_verified' => 1,
                    'is_requested' => 0,
                ])
            );
        }

        $conversations = collect();
        $timezone = 'Asia/Dhaka';

        // Create 10 conversations per agent
        foreach ($agents as $agent) {
            for ($i = 0; $i < 10; $i++) {
                $customer = $customers->random();
                $startedAt = Carbon::now($timezone)->subMinutes(rand(10, 500));

                $conversations->push(
                    Conversation::create([
                        'agent_id' => $agent->id,
                        'customer_id' => $customer->id,
                        'platform' => $customer->platform->name,
                        'started_at' => $startedAt,
                        'agent_assigned_at' => $startedAt->copy()->addMinutes(1),
                        'in_queue_at' => $startedAt->copy()->subMinutes(1),
                        'first_message_at' => null,
                        'last_message_at' => null,
                        'end_at' => $startedAt->copy()->addMinutes(rand(15, 120)),
                    ])
                );
            }
        }

        // Generate 20 messages per conversation
        foreach ($conversations as $conversation) {
            $firstMessageTime = $conversation->started_at->copy()->addMinute();
            $lastMessageTime = $firstMessageTime->copy();

            for ($i = 0; $i < 20; $i++) {
                $agent = $conversation->agent;
                $customer = $conversation->customer;
                $isAgentSender = $i % 2 === 0;

                $sender = $isAgentSender ? $agent : $customer;
                $receiver = $isAgentSender ? $customer : $agent;

                $text = $isAgentSender
                    ? $agentMessages[array_rand($agentMessages)]
                    : $customerMessages[array_rand($customerMessages)];

                $lastMessageTime->addMinutes(rand(1, 5));

                $message = Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $sender->id,
                    'sender_type' => get_class($sender),
                    'receiver_id' => $receiver->id,
                    'receiver_type' => get_class($receiver),
                    'content' => $text,
                    'type' => 'text',
                    'direction' => $isAgentSender ? 'outgoing' : 'incoming',
                    'delivered_at' => $lastMessageTime,
                    'read_at' => $lastMessageTime->copy()->addSeconds(rand(10, 120)),
                    'platform_message_id' => uniqid('msg_'),
                    'created_at' => $lastMessageTime,
                    'updated_at' => $lastMessageTime,
                ]);

                if (rand(0, 10) > 7) {
                    MessageAttachment::factory()->create(['message_id' => $message->id]);
                }

                if ($i === 0) {
                    $conversation->first_message_at = $lastMessageTime->copy();
                }

                $conversation->last_message_id = $message->id;
            }

            $conversation->last_message_at = $lastMessageTime->copy();
            $conversation->save();
        }
    }

    private function generateBangladeshiPhone(): string
    {
        return '+8801' . rand(3, 9) . rand(10000000, 99999999);
    }
}
