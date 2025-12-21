<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminPayHistory extends Model
{
    use HasFactory;
    protected $fillable = ['member_id', 'payment_name', 'tnx', 'amount', 'balance', 'method', 'type'];
}
