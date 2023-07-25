<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'text',
    ];
}
