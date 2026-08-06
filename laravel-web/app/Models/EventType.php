<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventType extends Model
{
    protected $fillable = [
        'name',
        'parent_id',
    ];

    public function events()
    {
        return $this->hasMany(Event::class, 'event_type_id');
    }
}
