@props(['status', 'label', 'type' => 'order'])
@php
    [$icon, $tone] = match ($status) {
        'created', 'pending', 'pending_payment' => ['clock', 'pending'],
        'paid' => [$type === 'payment' ? 'shield-check' : 'circle-check', 'positive'],
        'confirmed', 'completed' => ['circle-check', 'positive'],
        'preparing' => ['pot', 'progress'],
        'ready' => ['package-check', 'positive'],
        'out_for_delivery' => ['truck', 'progress'],
        'cancelled' => ['circle-x', 'danger'],
        'failed', 'rejected', 'abandoned', 'reversed', 'mismatch' => ['warning', 'danger'],
        default => ['orders', 'neutral'],
    };
@endphp
<span class="status-indicator status-indicator--{{ $tone }}"><x-icon :name="$icon"/> {{ $label }}</span>