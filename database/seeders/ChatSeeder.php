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
use App\Models\ConversationRating;
use Carbon\Carbon;

class ChatSeeder extends Seeder
{
    public function run(): void
    {
        $agents    = User::where('role_id', 4)->get();
        $platforms = Platform::whereIn('name', [
            'whatsapp',
            'facebook_messenger',
            'instagram_message'
        ])->get();
        $wrapUps   = WrapUpConversation::all();

        /* ---------------------------------------------------------
         * Bangladeshi Names & Phone Numbers
         * --------------------------------------------------------- */
        $bangladeshiNames = [
            "Mahadi Hassan", "Tanvir Rahman", "Nusrat Jahan", "Fahim Ahmed",
            "Anika Chowdhury", "Rafiq Islam", "Tania Akter", "Hasan Mahmud",
            "Sadia Sultana", "Rasel Karim", "Shuvo Kanti", "Afsana Khan",
            "Imran Hossain", "Rumana Begum", "Jahidul Islam", "Lamia Hossain"
        ];

        $bangladeshiPhonePrefix = ["+8801"]; // Bangladesh mobile prefix
        $bangladeshiPhoneNumbers = [];

        // Generate random Bangladesh mobile numbers
        for ($i = 0; $i < 50; $i++) {
            $bangladeshiPhoneNumbers[] = $bangladeshiPhonePrefix[array_rand($bangladeshiPhonePrefix)]
                . rand(3,9)
                . rand(10000000, 99999999);
        }

        $customerWords = [
            "hello", "price", "order", "delivery", "information", "help",
            "details", "issue", "product", "service", "payment", "available",
            "color", "size", "return", "exchange", "quality", "offer",
            "question", "thanks", "please", "need", "problem", "support"
        ];

        $agentWords = [
            "sure", "checking", "processing", "confirm", "assist", "helping",
            "response", "please wait", "thanks", "verified", "done", "okay",
            "let me check", "I can help", "solved", "updated", "noted",
            "checking now", "one moment", "appreciate", "thank you"
        ];

        $timezone = 'Asia/Dhaka';

        /* ---------------------------------------------------------
         * Create 20 Conversations
         * --------------------------------------------------------- */
        for ($c = 0; $c < 20; $c++) {

            $agent    = $agents->random();
            $platform = $platforms->random();

            // Bangladeshi name and phone
            $customerName  = $bangladeshiNames[array_rand($bangladeshiNames)];
            $customerPhone = $bangladeshiPhoneNumbers[array_rand($bangladeshiPhoneNumbers)];

            $customer = Customer::create([
                'name'            => $customerName,
                'phone'           => $customerPhone,
                'platform_user_id' => $platform->name === "facebook_messenger" ? uniqid("fb_") : null,
                'platform_id'      => $platform->id,
            ]);

            $startedAt = Carbon::now($timezone)->subMinutes(rand(200, 20000));
            $hasEnded  = rand(0, 1) === 1;
            $endAt     = $hasEnded ? $startedAt->copy()->addMinutes(rand(20, 200)) : null;

            $conversation = Conversation::create([
                'customer_id'       => $customer->id,
                'agent_id'          => $agent->id,
                'platform'          => $platform->name,
                'started_at'        => $startedAt,
                'agent_assigned_at' => $startedAt->copy()->addMinute(),
                'in_queue_at'       => $startedAt->copy()->subMinute(),
                'end_at'            => $endAt,
                'ended_by'          => $hasEnded ? $agent->id : null,
                'wrap_up_id'        => $hasEnded && $wrapUps->count() ? $wrapUps->random()->id : null,
                'is_feedback_sent'  => 0,
            ]);

            $lastMessageTime = $startedAt->copy()->addMinutes(1);
            $firstCustomerMsgAt  = null;
            $firstAgentReplyAt   = null;
            $foundFirstReply     = false;

            // Generate 30 messages
            for ($i = 1; $i <= 30; $i++) {
                $isAgent = $i % 2 === 0;
                $sender   = $isAgent ? $agent : $customer;
                $receiver = $isAgent ? $customer : $agent;

                $wordsBase = $isAgent ? $agentWords : $customerWords;
                shuffle($wordsBase);
                $sentence = ucfirst(implode(" ", array_slice($wordsBase, 0, rand(4, 9)))) . ". (msg-$i)";

                $lastMessageTime->addMinutes(rand(1, 4));

                $msg = Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_id'       => $sender->id,
                    'sender_type'     => get_class($sender),
                    'receiver_id'     => $receiver->id,
                    'receiver_type'   => get_class($receiver),
                    'content'         => $sentence,
                    'type'            => 'text',
                    'direction'       => $isAgent ? 'outgoing' : 'incoming',
                    'delivered_at'    => $lastMessageTime,
                    'read_at'         => $lastMessageTime->copy()->addSeconds(rand(10, 60)),
                    'platform_message_id' => uniqid('msg_'),
                    'created_at'      => $lastMessageTime,
                ]);

                if (!$isAgent && !$firstCustomerMsgAt) {
                    $firstCustomerMsgAt = $lastMessageTime->copy();
                }

                if ($firstCustomerMsgAt && $isAgent && !$foundFirstReply) {
                    $firstAgentReplyAt = $lastMessageTime->copy();
                    $foundFirstReply = true;
                }

                if (rand(1, 10) >= 8) {
                    MessageAttachment::factory()->create([
                        'message_id' => $msg->id,
                    ]);
                }

                $conversation->last_message_id = $msg->id;
            }

            $conversation->first_message_at  = $firstCustomerMsgAt;
            $conversation->last_message_at   = $lastMessageTime;
            $conversation->first_response_at = $firstAgentReplyAt;

            if ($hasEnded) {
                ConversationRating::create([
                    'conversation_id'  => $conversation->id,
                    'platform'         => $platform->name,
                    'option_label'     => ['Good','Excellent','Average'][rand(0,2)],
                    'rating_value'     => rand(3,5),
                    'interactive_type' => 'feedback',
                    'comments'         => 'Auto generated conversation rating.',
                ]);

                $conversation->is_feedback_sent = 1;
            }

            $conversation->save();
        }
    }
}
