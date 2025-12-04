<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberVerify extends Model
{

    protected $fillable = [
        'member_id',
        'type',
        'nid_number',
        'birth_number',
        'nid_front_image',
        'nid_back_image',
        'birth_image',
        'passport_image',
        'driving_front_image',
        'driving_back_image',
    ];
}
