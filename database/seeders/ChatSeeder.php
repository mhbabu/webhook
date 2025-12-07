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
        // 1. Fetch all Agents
        // -------------------------------------------
        $agents = User::where('role_id', 4)->get();

        // -------------------------------------------
        // 2. Get Platform IDs (whatsapp, messenger, instagram only)
        // -------------------------------------------
        $allowedPlatforms = Platform::whereIn('name', [
            'whatsapp',
            'facebook_messenger',
            'instagram_message'
        ])->pluck('id', 'name');

        // -------------------------------------------
        // 3. Bangladeshi Names
        // -------------------------------------------
        $bdNames = [
            'Mahmudul Hasan', 'Abdul Karim', 'Shakil Ahmed', 'Sabbir Hossain', 'Fahim Rahman',
            'Riaz Uddin', 'Tanmoy Islam', 'Arif Chowdhury', 'Mehedi Hasan', 'Ibrahim Khalil',
            'Nasrin Akter', 'Sharmin Sultana', 'Shathi Akter', 'Mim Chowdhury', 'Jannatul Ferdous',
            'Sumaiya Akter', 'Nusrat Jahan', 'Lamia Islam', 'Rubaida Rahman', 'Sadia Afrin'
        ];

        // -------------------------------------------
        // 4. Create 50 BD Customers
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
        // 5. Create 50 Conversations
        // -------------------------------------------
        $conversations = Conversation::factory(50)->create()->each(function ($conv) use ($agents, $customers) {
            $conv->update([
                'agent_id' => $agents->random()->id,
                'customer_id' => $customers->random()->id,
            ]);
        });

        // -------------------------------------------
        // 6. Create 20 Messages for EACH Conversation
        // -------------------------------------------
        foreach ($conversations as $conversation) {

            for ($i = 0; $i < 20; $i++) {

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

                MessageAttachment::factory()
                    ->count(rand(0, 2))
                    ->create(['message_id' => $message->id]);

                // update last message
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
