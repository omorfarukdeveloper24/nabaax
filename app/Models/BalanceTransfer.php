<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BalanceTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'password',
        'amount',
    ];

    public function sender()
    {
        return $this->belongsTo(Member::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(Member::class, 'receiver_id')
                    ->select('id', 'name', 'username','phone','image');
    }
}
