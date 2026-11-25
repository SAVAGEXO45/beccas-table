<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'active', 'position'];
    protected $attributes = ['active' => true, 'position' => 0];
    protected $casts = ['active' => 'boolean'];

    public function meals(): HasMany
    {
        return $this->hasMany(Meal::class);
    }
}

