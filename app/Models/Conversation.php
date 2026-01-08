<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'agent_id',
        'platform',
        'trace_id',
        'started_at',
        'end_at',
        'ended_by',
        'wrap_up_id',
        'in_queue_at',
        'first_message_at',
        'last_message_at',
        'agent_assigned_at',
        'last_message_id',
        'is_feedback_sent',
        'first_response_at',
        'post_id',
        'type',
        'type_id'
    ];

    protected $casts = [
        'started_at'        => 'datetime',
        'end_at'            => 'datetime',
        'in_queue_at'       => 'datetime',
        'first_message_at'  => 'datetime',
        'last_message_at'   => 'datetime',
        'agent_assigned_at' => 'datetime',
        'first_response_at' => 'datetime',
    ];

    // for reusing this filtering query
    public static function getConversationInfo(array $data)
    {
        $query = Conversation::with([
            'customer:id,name,email,phone,username',
            'agent:id,name,email,employee_id',
            'lastMessage:id,content,delivered_at,created_at',
            'wrapUp:id,name',
            'endedBy:id,name',
        ])->whereIn('platform', ['facebook_messenger', 'whatsapp', 'website', 'instagram_message'])->latest();

        if (! empty($data['start_date'])) {
            $query->whereDate('created_at', '>=', $data['start_date']);
        }

        if (! empty($data['end_date'])) {
            $query->whereDate('created_at', '<=', $data['end_date']);
        }

        return $query;
    }

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

    public function endedBy()
    {
        return $this->belongsTo(User::class, 'end_by');
    }

    public function systemMessages()
    {
        return $this->hasMany(ConversationTemplateMessage::class, 'conversation_id');
    }

    public function rating()
    {
        return $this->belongsTo(ConversationRating::class, 'conversation_id');
    }

    public function post()
    {
        return $this->hasOne(Post::class, 'id', 'post_id');
    }

    // Conversation.php

    public function comment()
    {
        return $this->belongsTo(PostComment::class, 'type_id', 'id');
    }

    public function reply()
    {
        return $this->belongsTo(PostCommentReply::class, 'type_id', 'id');
    }
}
