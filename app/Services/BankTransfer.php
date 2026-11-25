<?php

namespace App\Services;

use App\Mail\RestaurantMessage;
use App\Models\{Order, Setting, User};
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BankTransfer
{
    public function notify(Order $order, User $customer): void
    {
        DB::transaction(function () use ($order, $customer) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            abort_unless($order->user_id === $customer->id, 403);
            $payment = $order->payment()->lockForUpdate()->firstOrFail();
            abort_unless($payment->method === 'bank_transfer', 404);

            if ($payment->status !== 'pending' || $order->paid_at || $order->status !== 'pending_payment') {
                throw ValidationException::withMessages(['payment' => 'This transfer is no longer awaiting a payment notification.']);
            }
            if ($payment->transfer_notified_at) {
                return;
            }
            $payment->update(['transfer_notified_at' => now()]);
            RestaurantMessage::afterTransaction(
                Setting::current()['email'], 'Bank transfer awaiting review',
                "The customer reported a transfer for order {$order->number}. Check the bank statement before confirming receipt.",
                route('admin.orders.show', $order)
            );
        }, 3);
    }

    public function review(Order $order, User $staff, array $review): void
    {
        abort_unless($staff->is_admin && !$staff->is_disabled, 403);
        try {
            DB::transaction(function () use ($order, $staff, $review) {
                $order = Order::lockForUpdate()->findOrFail($order->id);
                $payment = $order->payment()->lockForUpdate()->firstOrFail();
                abort_unless($payment->method === 'bank_transfer', 404);

                if (!$payment->transfer_notified_at || $payment->status !== 'pending'
                    || $order->paid_at || $order->status !== 'pending_payment') {
                    throw ValidationException::withMessages(['payment' => 'This transfer no longer needs a decision.']);
                }
                $confirmed = $review['decision'] === 'confirm';
                $now = now();
                $payment->update([
                    'status' => $confirmed ? 'paid' : 'rejected',
                    'reviewed_by' => $staff->id,
                    'reviewed_at' => $now,
                    'verified_at' => $confirmed ? $now : null,
                    'bank_transaction_reference' => $confirmed ? strtoupper($review['bank_transaction_reference']) : null,
                    'rejection_reason' => $confirmed ? null : $review['rejection_reason'],
                ]);
                if ($confirmed) {
                    $order->update(['status' => 'confirmed', 'paid_at' => $now]);
                    $order->updates()->create(['status' => 'confirmed']);
                }
                RestaurantMessage::afterTransaction(
                    $order->email,
                    $confirmed ? 'Bank transfer confirmed' : 'Bank transfer rejected',
                    $confirmed
                        ? "We confirmed receipt of ".Setting::money($order->total)." for order {$order->number}."
                        : "Your transfer for order {$order->number} could not be confirmed. ".$payment->rejection_reason,
                    route('orders.show', $order)
                );
            }, 3);
        } catch (UniqueConstraintViolationException $error) {
            throw ValidationException::withMessages(['bank_transaction_reference' => 'This bank statement reference has already been used to confirm a transfer.']);
        }
    }
}
