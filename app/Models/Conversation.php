<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = [
        'name',
        'user_init_id',
    ];

    function conversationUser()
    {
        return $this->hasMany(ConversationUser::class, 'conversation_id', 'id');
    }
}
