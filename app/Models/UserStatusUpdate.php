<?php

namespace App\Models;

use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserStatusUpdate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'break_request_status',
        'reason',
        'request_at',
        'approved_at',
        'approved_by',
        'changed_at'
    ];

    protected $casts = [
        'status' => UserStatus::class,
    ];

    // Define relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
