<?php


namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\User;

class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        $customer = Customer::inRandomOrder()->first();
        $agent    = User::inRandomOrder()->first();

        return [
            'customer_id' => $customer->id,
            'agent_id'    => $agent->id,
            'platform'    => $customer->platform->name ?? 'whatsapp',
            'trace_id'    => $this->faker->uuid(),
            'started_at'  => now(),
            'ended_by'    => $this->faker->boolean(30) ? $agent->id : null, // sometimes ended
            'wrap_up_id'  => $this->faker->boolean(50) ? 1 : null, // optional wrap up
            'end_at'      => $this->faker->boolean(30) ? now() : null,
        ];
    }
}
