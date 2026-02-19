<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Earning extends Model
{
    // এই অংশটুকু যোগ করুন
    protected $fillable = [
        'member_id',
        'amount',
        'new_views',
        'new_watch_time',
        'earning_date'
    ];
}
