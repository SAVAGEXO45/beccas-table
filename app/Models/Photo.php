<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Photo extends Model
{
    protected $fillable = ['path', 'width', 'height', 'alt', 'credit', 'show_in_gallery', 'illustrative'];
    protected $casts = ['show_in_gallery' => 'boolean', 'illustrative' => 'boolean'];

    public function url(?int $width = null): string
    {
        return Storage::disk('public')->url($width ? str_replace('.webp', "-$width.webp", $this->path) : $this->path);
    }

    public function sourceSet(): string
    {
        $sources = [];
        foreach ([480, 960, 1600] as $limit) {
            $scale = min(1, $limit / max($this->width, $this->height));
            $width = max(1, (int) round($this->width * $scale));
            $sources[$width] ??= $this->url($limit === 1600 ? null : $limit)." {$width}w";
        }

        return implode(', ', $sources);
    }
}


