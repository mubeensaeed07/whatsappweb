<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
    protected $fillable = [
        'message_id',
        'chat_id',
        'contact_name',
        'from_number',
        'to_number',
        'body',
        'from_me',
        'received_at',
        'read_at',
        'payload',
    ];

    protected $casts = [
        'from_me' => 'boolean',
        'received_at' => 'datetime',
        'read_at' => 'datetime',
        'payload' => 'array',
    ];
}
