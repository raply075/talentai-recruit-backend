<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Resume extends Model
{
   protected $fillable = [
    'user_id',
    'title',
    'original_name',
    'file_path',
    'file_size',
    'ats_score',
    'career_level',
    'skills',
    'suggestions',
    'summary',
];

protected $casts = [
    'skills' => 'array',
    'suggestions' => 'array',
];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}