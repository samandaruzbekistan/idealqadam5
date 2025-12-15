<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Registration extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_id',
        'full_name',
        'school',
        'grade',
        'subjects',
        'is_subscribed',
        'state',
    ];

    protected $casts = [
        'is_subscribed' => 'boolean',
        'grade' => 'integer',
    ];
}
