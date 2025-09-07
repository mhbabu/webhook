<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageAttachment extends Model
{
    // protected $table = "message_attchemnts";
    protected $fillable = ['message_id', 'type', 'url', 'file_name'];
    
    public function message() { 
        return $this->belongsTo(Message::class); 
    }
}
