<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'name',
        'value',
        'type',
        'parent_id',
    ];

    public function scopeForGym($query, int $parentId)
    {
        return $query->where('parent_id', $parentId);
    }

    public function scopeByName($query, string $name)
    {
        return $query->where('name', $name);
    }

    /**
     * Get a setting value by name and gym ID
     */
    public static function getValue(string $name, int $parentId, $default = null)
    {
        $setting = static::where('name', $name)
                        ->where('parent_id', $parentId)
                        ->first();
        
        return $setting ? $setting->value : $default;
    }

    /**
     * Set a setting value
     */
    public static function setValue(string $name, $value, int $parentId): void
    {
        // settings.value is NOT nullable on the live DB. Laravel converts empty
        // form inputs to null, so normalize null to empty string before saving.
        if ($value === null) {
            $value = '';
        }

        static::updateOrCreate(
            ['name' => $name, 'parent_id' => $parentId],
            ['value' => (string) $value, 'type' => 'text']
        );
    }
}
