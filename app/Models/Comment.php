<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use SoftDeletes;
    protected $fillable = ['post_id', 'member_id', 'parent_id', 'content'];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class)->select('id', 'name', 'username', 'image');
    }

    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }
    
    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id')
                    ->with(['member', 'replies']);
    }

    



    
}
