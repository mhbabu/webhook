<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageAttachment extends Model
{
    use HasFactory;

    protected $table = 'message_attachments';

    protected $fillable = [
        'message_id',
        'type',
        'path',
        'mime',
        'size',
        'attachment_id',
        'is_available',
    ];

    protected $casts = [
        'is_available' => 'boolean',
    ];

    public function message()
    {
        return $this->belongsTo(Message::class);
    }
}
