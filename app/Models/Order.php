<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

class Order extends Model
{
    public const STATUSES = ['pending_payment', 'paid', 'confirmed', 'preparing', 'ready', 'out_for_delivery', 'completed', 'cancelled'];

    protected $fillable = ['user_id', 'number', 'status', 'name', 'email', 'phone', 'fulfilment', 'address', 'delivery_area', 'delivery_instructions', 'subtotal', 'delivery_fee', 'total', 'currency', 'paid_at'];
    protected $casts = ['paid_at' => 'datetime'];

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function updates(): HasMany
    {
        return $this->hasMany(OrderUpdate::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function transitions(): array
    {
        if (!$this->paid_at) {
            return [];
        }

        return match ($this->status) {
            'paid' => ['confirmed', 'cancelled'],
            'confirmed' => ['preparing', 'cancelled'],
            'preparing' => ['ready'],
            'ready' => $this->fulfilment === 'delivery' ? ['out_for_delivery'] : ['completed'],
            'out_for_delivery' => ['completed'],
            default => [],
        };
    }

    public function progressLabel(string $status): string
    {
        return $status === 'ready'
            ? ($this->fulfilment === 'delivery' ? 'Ready for dispatch' : 'Ready for pickup')
            : self::label($status);
    }

    public static function label(string $status): string
    {
        return ucwords(str_replace('_', ' ', $status));
    }
}

