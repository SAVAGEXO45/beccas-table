<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealAddon extends Model
{
    protected $fillable = ['name', 'price', 'available', 'position'];
    protected $casts = ['price' => 'integer', 'available' => 'boolean'];

    public function meal(): BelongsTo
    {
        return $this->belongsTo(Meal::class);
    }
}
