<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MessageTemplate;

class MessageTemplateTableSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'type'      => 'welcome',
                'content'   => 'Hey there ! Welcome to Samsung Whatsapp Customer Support. Kindly enter your Full Name & Email Id. ☺️',
                'logo_path' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRM0wm_0xLSPMVFcv3JMJ6NkjaLAdurZS1YfBW_0bhUbw7MPbNMLtdMIfU&s',
                'remarks'   => 'Welcome message for all customers on their first conversation.',
                'is_active' => true,
            ],
            [
                'type'      => 'agent_assigned',
                'content'   => 'joined the session. Thank you for contacting us. I will be glad to assist you',
                'remarks'   => 'Sent automatically when an agent is assigned.',
                'is_active' => true,
            ],
            [
                'type'      => 'alert',
                'content'   => 'Hello, are you there?',
                'remarks'   => 'Sent automatically after 2 minutes of customer inactivity.',
                'is_active' => true,
            ],
            [
                'type'      => 'second_alert',
                'content'   => 'As we are not getting any response from your end, we are closing the chat to support next customer. However we are here to support you with 24/7 chat support. Please knock us again at your convenient time.',
                'remarks'   => 'Sent automatically when agent have no response after alert message.',
                'is_active' => true,
            ],
            [
                'type'      => 'third_alert',
                'content'   => 'Customer like you make Samsung, Thank you for contacting Samsung. Have a great day. Please stay safe ! Thank you for contacting Samsung Support ! Have a nice day☺',
                'remarks'   => 'Sent automatically when agent have no response after alert message.',
                'is_active' => true,
            ],
            [
                'type'      => 'end_chat',
                'content'   => 'Thanks for the feedback. We sincerely appreciate your insight',
                'remarks'   => 'Sent after the chat session ends.',
                'is_active' => true,
            ],
            [
                'type'      => 'cchat',
                'content'   => 'Kindly rate your experience with our specialist during this conversation.',
                'remarks'   => 'Sent automatically after end_chat message.',
                'is_active' => true,
                'options'   => [
                    ['label' => 'Excellent', 'emoji' => '😊', 'value' => 5],
                    ['label' => 'Good', 'emoji'      => '🙂', 'value' => 4],
                    ['label' => 'Average', 'emoji'   => '😐', 'value' => 3],
                    ['label' => 'Bad', 'emoji'       => '🙁', 'value' => 2],
                    ['label' => 'Very Bad', 'emoji'  => '😢', 'value' => 1],
                ],
            ],
            [
                'type'      => 'after_feedback',
                'content'   => 'Thaks for the feedback. We sincerely appreciate your insight',
                'remarks'   => 'After customer feedback will send this message',
                'is_active' => true,
            ],
        ];

        foreach ($templates as $data) {
            MessageTemplate::updateOrCreate(
                ['type' => $data['type']], // prevents duplicates
                $data
            );
        }
    }
}
