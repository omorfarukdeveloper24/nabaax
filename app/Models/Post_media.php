<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Post_media extends Model
{
    use HasFactory;

     protected $fillable = [
        'post_id',
        'media_type',
        'path',
        'duration',
        'thumbnail_path',
        'total_views',
    ];

    // Accessor to get full URL
    public function getFileUrlAttribute()
    {
        return asset($this->path);
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
