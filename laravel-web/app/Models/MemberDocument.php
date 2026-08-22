<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberDocument extends Model
{
    protected $fillable = ['user_id', 'doc_type', 'file_path'];

    protected $appends = ['url'];

    public function getUrlAttribute(): ?string
    {
        return $this->file_path ? url('uploads/' . ltrim($this->file_path, '/')) : null;
    }
}
