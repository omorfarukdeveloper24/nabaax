<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id', 'content', 'type', 'visibility', 'group_id',
        'is_pinned', 'scheduled_at', 'is_edited','boost_amount','message_link','website_link','click_cost','boost_status'
    ];

    // Relations
    public function member()
    {
        return $this->belongsTo(Member::class)->select('id', 'name', 'image', 'username','verified');
    }
    public function views()
    {
        return $this->hasMany(PostView::class);
    }

    public function media()
    {
        return $this->hasMany(Post_media::class);
    }
    public function boost()
    {
        return $this->hasOne(PostBoost::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function likes()
    {
        return $this->hasMany(Like::class);
    }

    public function shares()
    {
        return $this->hasMany(Share::class);
    }

    public function stats()
    {
        return $this->hasOne(PostStat::class);
    }


}
