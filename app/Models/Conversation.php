<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = ['name', 'description', 'is_group', 'avatar'];

    protected $casts = [
        'is_group' => 'boolean',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'conversation_users')
                    ->withPivot('role')
                    ->withTimestamps();
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }
}
