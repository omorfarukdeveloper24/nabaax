<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Deposit extends Model
{
     use HasFactory;
     
    protected $fillable = [
        'member_id',
        'amount',
        'method',
        'sender_number',
        'tnx_id',
        'status',
    ];

    
    public function member()
    {
        return $this->belongsTo(Member::class);
    }
    
    
}
