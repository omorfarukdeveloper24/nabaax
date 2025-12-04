<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WalletWithdraw extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'amount',
        'method',
        'receiver_number',
        'password',
        'status',
    ];

    
    public function member()
    {
        return $this->belongsTo(Member::class);
    }
    
}
