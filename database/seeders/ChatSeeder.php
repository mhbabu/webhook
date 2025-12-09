<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Customer;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\Platform;
use App\Models\WrapUpConversation;
use Carbon\Carbon;

class ChatSeeder extends Seeder
{
    public function run(): void
    {
        $agents    = User::where('role_id', 4)->get();
        $platforms = Platform::whereIn('name', ['whatsapp', 'facebook_messenger', 'instagram_message'])->get();
        $wrapUps   = WrapUpConversation::all();

        $bdNames = [
            'Mahmudul Hasan','Abdul Karim','Shakil Ahmed','Sabbir Hossain','Fahim Rahman',
            'Riaz Uddin','Tanmoy Islam','Arif Chowdhury','Mehedi Hasan','Ibrahim Khalil',
            'Nasrin Akter','Sharmin Sultana','Shathi Akter','Mim Chowdhury','Jannatul Ferdous',
            'Sumaiya Akter','Nusrat Jahan','Lamia Islam','Rubaida Rahman','Sadia Afrin'
        ];

        $customerMessages = [
            "Hello, how are you?", "I need some help.", "Ami ekta product er price jante chai.",
            "Delivery kotodin lage?", "Order dite chai.", "Apnader service ta valo.", "Ekta problem hocche.",
            "Can you help me please?", "Thanks!", "Ami ki ekto details pete pari?"
        ];

        $agentMessages = [
            "Hello! How can I help you today?", "Sure, I can assist you.", "Can you please share more details?",
            "Your order is being processed.", "Let me check for you.", "Thanks for reaching out.",
            "Anything else I can help you with?", "Please wait a moment.", "Your issue is resolved.",
            "Thank you for contacting us!"
        ];

        $timezone = 'Asia/Dhaka';
        $customers = collect();

        // Create 1000 customers
        for ($i = 0; $i < 1000; $i++) {
            $name = $bdNames[array_rand($bdNames)];
            $platform = $platforms->random();

            $customers->push(Customer::create([
                'name' => $name,
                'username' => strtolower(str_replace(' ', '_', $name)) . uniqid(),
                'email' => 'customer' . rand(1000, 999999) . '@example.com',
                'phone' => $this->generateBangladeshiPhone(),
                'platform_user_id' => $platform->name === 'facebook_messenger' ? uniqid('fb_user_') : null,
                'platform_id' => $platform->id,
                'profile_photo' => null,
                'is_verified' => 1,
                'is_requested' => 0,
            ]));
        }

        $conversations = collect();

        // Create 1000 conversations
        for ($i = 0; $i < 1000; $i++) {
            $agent = $agents->random();
            $customer = $customers->random();
            $startedAt = Carbon::now($timezone)->subMinutes(rand(10, 10000));

            // Randomly decide if this conversation has ended
            $hasEnded = rand(0, 1) === 1;
            $endAt = $hasEnded ? $startedAt->copy()->addMinutes(rand(15, 120)) : null;
            $endedBy = $hasEnded ? $agent->id : null;
            $wrapUpId = $hasEnded && $wrapUps->count() ? $wrapUps->random()->id : null;

            $conversations->push(Conversation::create([
                'agent_id' => $agent->id,
                'customer_id' => $customer->id,
                'platform' => $customer->platform->name,
                'started_at' => $startedAt,
                'agent_assigned_at' => $startedAt->copy()->addMinute(),
                'in_queue_at' => $startedAt->copy()->subMinute(),
                'first_message_at' => null,
                'last_message_at' => null,
                'end_at' => $endAt,
                'ended_by' => $endedBy,
                'wrap_up_id' => $wrapUpId,
            ]));
        }

        // Generate 50 messages per conversation
        foreach ($conversations as $conversation) {
            $lastMessageTime = $conversation->started_at->copy()->addMinute();

            for ($i = 0; $i < 50; $i++) {
                $isAgentSender = $i % 2 === 0;
                $sender = $isAgentSender ? $conversation->agent : $conversation->customer;
                $receiver = $isAgentSender ? $conversation->customer : $conversation->agent;

                $text = $isAgentSender ? $agentMessages[array_rand($agentMessages)] : $customerMessages[array_rand($customerMessages)];

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
        $prefixes = ['+88017', '+88018', '+88019', '+88016', '+88015'];
        $prefix = $prefixes[array_rand($prefixes)];
        return $prefix . str_pad(rand(0, 99999999), 8, '0', STR_PAD_LEFT);
    }
}
