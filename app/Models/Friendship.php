<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Friendship extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'status',
    ];

    public function sender()
    {
        return $this->belongsTo(Member::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(Member::class, 'receiver_id');
    }
    
}
