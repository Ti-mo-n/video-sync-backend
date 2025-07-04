<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    protected $fillable = [
        'title', 'path', 'thumbnail', 'description',
        'mime_type', 'size', 'duration'
    ];
}
