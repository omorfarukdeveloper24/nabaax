<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Share extends Model
{
    protected $fillable = ['post_id', 'member_id', 'type', 'comment'];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
