<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'member_id',
        'title',
        'description',
        'firebase_id',
        'notification_id',
        'notification_type',
        'status',
    ];
}
