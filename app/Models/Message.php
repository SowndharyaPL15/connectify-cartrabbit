<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'body',
        'image_path',
        'type',
        'status',
        'is_edited',
        'deleted_by',
        'is_pinned',
        'starred_by',
        'favorited_by',
        'reply_to_id',
        'forwarded_from_id',
    ];

    protected $casts = [
        'is_edited' => 'boolean',
        'is_pinned' => 'boolean',
        'deleted_by' => 'array',
        'starred_by' => 'array',
        'favorited_by' => 'array',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function replyTo()
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }

    public function forwardedFrom()
    {
        return $this->belongsTo(Message::class, 'forwarded_from_id');
    }

    public function reactions()
    {
        return $this->hasMany(MessageReaction::class);
    }

    public function reactionsSummary($authUserId)
    {
        $reactions = $this->reactions()->get();
        $summary = [];
        $userReaction = null;

        foreach ($reactions as $r) {
            $summary[$r->emoji] = ($summary[$r->emoji] ?? 0) + 1;
            if ($r->user_id === $authUserId) {
                $userReaction = $r->emoji;
            }
        }

        return [
            'summary' => $summary,
            'userReaction' => $userReaction,
        ];
    }

    public function getImageUrlAttribute(): ?string
    {
        if ($this->image_path) {
            return asset('uploads/messages/' . $this->image_path);
        }
        return null;
    }

    public function getTimeAttribute(): string
    {
        return $this->created_at->format('g:i A');
    }

    public function getDateLabelAttribute(): string
    {
        if ($this->created_at->isToday()) return 'Today';
        if ($this->created_at->isYesterday()) return 'Yesterday';
        return $this->created_at->format('j M, Y');
    }
}
