<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WrapUpConversation>
 */
class WrapUpConversationFactory extends Factory
{
    public function definition(): array
    {
        $reasons = [
            'Customer did not respond',
            'Internet connection lost',
            'Agent\'s shift ended',
            'Customer ended the conversation',
            'Issue resolved',
            'Customer disconnected',
            'Customer will call back later',
            'Escalated to supervisor',
            'Technical issue – unable to proceed',
            'Call dropped',
            'No longer interested',
            'Language barrier',
            'Customer became unresponsive',
            'Requested follow-up email',
            'Customer was in a hurry',
            'Message transferred to another department',
            'Agent had an emergency',
            'Scheduled callback',
            'Customer resolved issue themselves',
            'Agent replied and the conversation was ended',
        ];

        return [
            'name' => $this->faker->randomElement($reasons),
        ];
    }
}
