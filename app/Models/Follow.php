<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Follow extends Model
{
    use HasFactory;

    protected $fillable = ['follower_id', 'following_id', 'type', 'is_friend' ];

    public function follower()
    {
        return $this->belongsTo(Member::class, 'follower_id');
    }

    public function following()
    {
        return $this->belongsTo(Member::class, 'following_id');
    }
}
