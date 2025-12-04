<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MiniAd extends Model
{
     protected $fillable = ['title', 'image', 'link', 'member_id', 'status'];

    
}
