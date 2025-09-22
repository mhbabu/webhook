<?php
namespace App\Models;

use App\Http\Resources\CustomerResource;
use App\Http\Resources\User\UserResource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id', 'agent_id', 'platform', 'trace_id',
        'started_at', 'end_at', 'ended_by', 'wrap_up_id', 'last_message_id'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }

    public function lastMessage()
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    public function wrapUp()
    {
        return $this->belongsTo(WrapUpConversation::class, 'wrap_up_id', 'id');
    }
}
