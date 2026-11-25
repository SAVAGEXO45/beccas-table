<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealVariation extends Model
{
    protected $fillable = ['name', 'size', 'protein', 'price', 'available', 'position'];
    protected $casts = ['price' => 'integer', 'available' => 'boolean'];

    public function meal(): BelongsTo
    {
        return $this->belongsTo(Meal::class);
    }

    public function label(): string
    {
        return implode(' · ', array_filter([$this->name, $this->size, $this->protein]));
    }
}
