<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Permission\Traits\HasRoles;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Models\Role;

// use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasMedia
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes, HasRoles, InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'name',
        'email',
        'mobile',
        'password',
        'current_status',
        'max_limit',
        'current_limit',
        'account_status',
        'is_verified',
        'is_request',
        'created_by',
        'deleted_by',
        'role_id',
        'is_password_updated',
        'password_updated_at'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'status' => UserStatus::class,
    ];

    /**
     * Boot the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // Automatically set 'created_by' before insert
        static::creating(function ($user) {
            if (auth()->check()) {
                $user->created_by = auth()->id();
            }
        });

        // Automatically set 'updated_by' on updates
        static::updating(function ($user) {
            if (auth()->check()) {
                $user->updated_by = auth()->id();
            }
        });

        static::deleting(function ($user) {
            if (auth()->check() && $user->exists) {
                $user->deleted_by = auth()->id();
                $user->saveQuietly(); // persist deleted_by without triggering events
            }
        });
    }

    /**
     * Register media collections for the user.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('profile_pictures');
    }


    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'status'            => UserStatus::class
        ];
    }

    /**
     * Get the user's platforms.
     */
    public function platforms()
    {
        return $this->belongsToMany(Platform::class, 'platform_user', 'user_id', 'platform_id'); // 'user_id', 'platform_id' optional
    }

    /**
     * Get the user's role.
     */
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function messagesSent()
    {
        return $this->morphMany(Message::class, 'sender');
    }

    public function messagesReceived()
    {
        return $this->morphMany(Message::class, 'receiver');
    }

    public function userStatusInfo()
    {
        return $this->hasOne(UserStatusUpdate::class, 'user_id')->latest()->select('id', 'user_id', 'status', 'break_request_status', 'reason', 'request_at', 'changed_at');
    }

}
