<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PostBoost extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id', 'age_from', 'age_to', 'start_date', 'end_date',
        'gender', 'location', 'profession', 'income_range','boost_amount','remaining_amount','message_link','website_link','click_cost','status','post_id'
    ];

}
