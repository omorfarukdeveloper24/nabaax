<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Memberbackup extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'backup_data',
        'updated_fields',
    ];

    protected $casts = [
        'backup_data' => 'array',
        'updated_fields' => 'array',
    ];
}
