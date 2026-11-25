<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const METHODS = ['paystack' => 'Paystack', 'bank_transfer' => 'Bank transfer'];
    public const STATUSES = ['pending', 'paid', 'failed', 'rejected', 'abandoned', 'reversed', 'mismatch'];

    protected $attributes = ['method' => 'paystack'];
    protected $fillable = [
        'reference', 'method', 'status', 'provider_status', 'provider_id', 'channel', 'checkout_url', 'verified_at',
        'bank_details', 'transfer_notified_at', 'reviewed_by', 'reviewed_at', 'bank_transaction_reference', 'rejection_reason',
    ];
    protected $hidden = ['checkout_url', 'bank_details'];
    protected $casts = [
        'verified_at' => 'datetime', 'bank_details' => 'array', 'transfer_notified_at' => 'datetime', 'reviewed_at' => 'datetime',
    ];

    public function methodLabel(): string
    {
        return self::METHODS[$this->method];
    }

    public function statusLabel(): string
    {
        return Order::label($this->status === 'created' ? 'pending' : $this->status);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
