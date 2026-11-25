<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany};
use Illuminate\Support\Collection;

class Meal extends Model
{
    public const MENU_RELATIONS = ['photo', 'photos', 'category', 'variations', 'addons'];

    protected $fillable = ['name', 'slug', 'category_id', 'photo_id', 'description', 'preparation', 'price', 'available', 'featured', 'popular', 'price_confirmed', 'position'];
    protected $attributes = ['price_confirmed' => true, 'popular' => false, 'position' => 0];
    protected $casts = ['price' => 'integer', 'available' => 'boolean', 'featured' => 'boolean', 'popular' => 'boolean', 'price_confirmed' => 'boolean'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(Photo::class)->withPivot('position')->orderByPivot('position')->orderBy('photos.id');
    }

    public function variations(): HasMany
    {
        return $this->hasMany(MealVariation::class)->orderBy('position')->orderBy('id');
    }

    public function addons(): HasMany
    {
        return $this->hasMany(MealAddon::class)->orderBy('position')->orderBy('id');
    }

    public function gallery(): Collection
    {
        return $this->photos->collect()->prepend($this->photo)->filter()->unique('id')->values();
    }

    public function canOrder(): bool
    {
        return $this->available && $this->category->active && $this->price_confirmed
            && ($this->variations->isEmpty() || $this->variations->contains('available', true));
    }

    public function displayPrice(): int
    {
        $options = $this->variations->where('available', true);

        return $options->isEmpty() ? $this->price : $options->min('price');
    }
}
