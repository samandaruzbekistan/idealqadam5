<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudyCenterRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_id',
        'full_name',
        'subjects',
        'phone',
        'is_subscribed',
        'state',
    ];

    protected $casts = [
        'is_subscribed' => 'boolean',
    ];
}
