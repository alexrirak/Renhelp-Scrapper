<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tutorial extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'title',
        'content_html',
        'content_markdown',
        'srcUrl',
        'author',
        'level_number',
        'level_label',
        'tags',
    ];

    protected $casts = [
        'tags' => 'array',
    ];
}
