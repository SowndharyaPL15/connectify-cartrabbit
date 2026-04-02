<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'profile_photo',
        'about',
        'status',
        'last_seen',
        'google_id',
        'google_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_seen' => 'datetime',
        'password' => 'hashed',
    ];

    public function conversations()
    {
        return $this->belongsToMany(Conversation::class, 'conversation_users')
                    ->withPivot('role')
                    ->withTimestamps();
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class, 'user_id');
    }

    public function blockedUsers()
    {
        return $this->belongsToMany(User::class, 'blocks', 'user_id', 'blocked_id')->withTimestamps();
    }

    public function blockedBy()
    {
        return $this->belongsToMany(User::class, 'blocks', 'blocked_id', 'user_id')->withTimestamps();
    }

    public function hasBlocked($userId): bool
    {
        return $this->blockedUsers()->where('blocked_id', $userId)->exists();
    }

    public function isBlockedBy($userId): bool
    {
        return $this->blockedBy()->where('user_id', $userId)->exists();
    }

    public function getProfilePhotoUrlAttribute(): string
    {
        if ($this->profile_photo) {
            return asset('uploads/profiles/' . $this->profile_photo);
        }
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=25D366&color=fff&size=128';
    }

    public function getLastSeenTextAttribute(): string
    {
        if ($this->status === 'online') {
            return 'online';
        }
        if ($this->last_seen) {
            $diff = now()->diffInMinutes($this->last_seen);
            if ($diff < 1) return 'last seen just now';
            if ($diff < 60) return 'last seen ' . $diff . ' min ago';
            if ($diff < 1440) return 'last seen ' . now()->diffInHours($this->last_seen) . 'h ago';
            return 'last seen ' . $this->last_seen->format('d M');
        }
        return 'last seen recently';
    }
}
