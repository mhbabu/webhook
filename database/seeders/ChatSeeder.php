<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Customer;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\Platform;

class ChatSeeder extends Seeder
{
    public function run(): void
    {
        // -------------------------------------------
        // 1. Fetch all Agents (role_id=4)
        // -------------------------------------------
        $agents = User::where('role_id', 4)->get();

        // -------------------------------------------
        // 2. Allowed Platforms (WhatsApp, Messenger, Instagram)
        // -------------------------------------------
        $allowedPlatforms = Platform::whereIn('name', [
            'whatsapp',
            'facebook_messenger',
            'instagram_message'
        ])->pluck('id', 'name');

        // -------------------------------------------
        // 3. BD Names
        // -------------------------------------------
        $bdNames = [
            'Mahmudul Hasan','Abdul Karim','Shakil Ahmed','Sabbir Hossain','Fahim Rahman',
            'Riaz Uddin','Tanmoy Islam','Arif Chowdhury','Mehedi Hasan','Ibrahim Khalil',
            'Nasrin Akter','Sharmin Sultana','Shathi Akter','Mim Chowdhury','Jannatul Ferdous',
            'Sumaiya Akter','Nusrat Jahan','Lamia Islam','Rubaida Rahman','Sadia Afrin'
        ];

        // -------------------------------------------
        // 4. Customer Message Pool
        // -------------------------------------------
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

        // -------------------------------------------
        // 5. Agent Message Pool
        // -------------------------------------------
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

        // -------------------------------------------
        // 6. Create Customers (50)
        // -------------------------------------------
        $platformKeys = $allowedPlatforms->keys()->toArray();
        $customers = collect();

        for ($i = 0; $i < 50; $i++) {

            $platformName = $platformKeys[array_rand($platformKeys)];

            $customers->push(Customer::create([
                'name' => $bdNames[array_rand($bdNames)],
                'username' => null,
                'email' => 'customer' . rand(1000, 9999) . '@example.com',
                'phone' => $this->generateBangladeshiPhone(),
                'platform_user_id' => uniqid('user_'),
                'platform_id' => $allowedPlatforms[$platformName],
                'profile_photo' => null,
                'is_verified' => 1,
                'is_requested' => 0,
            ]));
        }

        // -------------------------------------------
        // 7. Generate Conversations (5 per Agent)
        // -------------------------------------------
        $conversations = collect();

        foreach ($agents as $agent) {
            for ($i = 0; $i < 5; $i++) {
                $conversations->push(
                    Conversation::create([
                        'agent_id' => $agent->id,
                        'customer_id' => $customers->random()->id,
                    ])
                );
            }
        }

        // -------------------------------------------
        // 8. Create 10 Meaningful Messages per Conversation
        // -------------------------------------------
        foreach ($conversations as $conversation) {

            for ($i = 0; $i < 10; $i++) {

                $agent = $conversation->agent;
                $customer = $conversation->customer;

                $isAgentSender = $i % 2 === 0; // alternate sender

                $sender = $isAgentSender ? $agent : $customer;
                $receiver = $isAgentSender ? $customer : $agent;

                $text = $isAgentSender
                    ? $agentMessages[array_rand($agentMessages)]
                    : $customerMessages[array_rand($customerMessages)];

                $message = Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $sender->id,
                    'sender_type' => get_class($sender),
                    'receiver_id' => $receiver->id,
                    'receiver_type' => get_class($receiver),
                    'content' => $text,
                    'direction' => $isAgentSender ? 'outgoing' : 'incoming',
                ]);

                if (rand(0, 10) > 7) {
                    MessageAttachment::factory()->create([
                        'message_id' => $message->id,
                    ]);
                }

                $conversation->update([
                    'last_message_id' => $message->id,
                ]);
            }
        }
    }

    private function generateBangladeshiPhone(): string
    {
        return '+8801' . rand(3, 9) . rand(10000000, 99999999);
    }
}
