<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConversationUser extends Model
{
    protected $fillable = [
        'user_id',
        'conversation_id',
    ];
}
