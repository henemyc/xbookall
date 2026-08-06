<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NoticeBoard extends Model
{
    protected $table = 'notice_boards';

    protected $fillable = [
        'title',
        'description',
        'attachment',
        'parent_id',
    ];

    public function scopeForGym($query, int $parentId)
    {
        return $query->where('parent_id', $parentId);
    }
}
