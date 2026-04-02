<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $fillable = ['user_id', 'contact_id', 'alias_name'];

    public function user()
    {
        return $this->belongsTo(User::class, 'contact_id'); // This ties the 'contact' to their actual User record
    }
}
