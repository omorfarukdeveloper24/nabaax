<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post_stat extends Model
{
    use HasFactory;
    protected $guarded = [];  
    

    // Relation: PostStat belongs to Post
    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
