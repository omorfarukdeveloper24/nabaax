<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoView extends Model
{
    protected $fillable = ['member_id', 'post_media_id', 'watch_time','viewed_at'];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function postMedia()
    {
        return $this->belongsTo(Post_media::class);
    }
}
