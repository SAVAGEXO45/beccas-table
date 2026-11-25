<?php

namespace App\Services;

use App\Mail\RestaurantMessage;
use App\Models\{Order, Payment, PaymentEvent, Setting};
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Http, Log};
use RuntimeException;

class Paystack
{
    public const NGN_MINIMUM = 5000;

    public function configured(): bool
    {
        return filled(config('restaurant.paystack.secret_key'))
            && rtrim((string) config('restaurant.paystack.api_url'), '/') === 'https://api.paystack.co';
    }

    private function client(): PendingRequest
    {
        $url = rtrim(config('restaurant.paystack.api_url'), '/');
        if (!$this->configured()) {
            throw new RuntimeException('Paystack configuration is unavailable.');
        }

        return Http::baseUrl($url)
            ->acceptJson()
            ->withToken(config('restaurant.paystack.secret_key'))
            ->withoutRedirecting()
            ->connectTimeout(3)
            ->timeout(8);
    }

    public function start(Payment $payment): ?string
    {
        if (!$this->configured() || $payment->method !== 'paystack' || in_array($payment->status, ['paid', 'failed', 'abandoned', 'reversed', 'mismatch'], true)) {
            return null;
        }
        if ($payment->checkout_url) {
            return $payment->checkout_url;
        }

        $order = $payment->order;
        try {
            $response = $this->client()->post('/transaction/initialize', [
                'reference' => $payment->reference,
                'email' => $order->email,
                'amount' => $order->total,
                'currency' => $order->currency,
                'callback_url' => route('paystack.callback'),
                'metadata' => ['cancel_action' => route('orders.show', $order)],
            ]);
        } catch (ConnectionException|RuntimeException $exception) {
            Log::warning('Paystack initialization unavailable.', ['reference' => $payment->reference]);

            return null;
        }

        $url = $response->json('data.authorization_url');
        if (!$response->successful() || $response->json('status') !== true
            || $response->json('data.reference') !== $payment->reference
            || !is_string($url) || !preg_match('~^https://checkout\.paystack\.com/[A-Za-z0-9_-]+$~D', $url)) {
            Log::warning('Paystack initialization not confirmed.', ['reference' => $payment->reference, 'http' => $response->status()]);

            return null;
        }

        $updated = Payment::whereKey($payment->id)->whereIn('status', ['created', 'pending'])->whereNull('checkout_url')->update([
            'checkout_url' => $url,
            'status' => 'pending',
        ]);

        return $updated ? $url : null;
    }

    public function verify(Payment $payment, ?string $fingerprint = null): bool
    {
        if ($payment->method !== 'paystack') {
            return false;
        }
        if ($fingerprint && PaymentEvent::where('fingerprint', $fingerprint)->exists()) {
            return true;
        }
        if ($payment->status === 'paid' && !$fingerprint) {
            return true;
        }

        try {
            $response = $this->client()->get('/transaction/verify/'.rawurlencode($payment->reference));
        } catch (ConnectionException|RuntimeException $exception) {
            return false;
        }
        $data = $response->json('data');
        if (!$response->successful() || $response->json('status') !== true || !is_array($data)) {
            return false;
        }

        try {
            return DB::transaction(function () use ($payment, $data, $fingerprint) {
                $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
                $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
                if ($fingerprint && PaymentEvent::where('fingerprint', $fingerprint)->exists()) {
                    return true;
                }

                $status = $data['status'] ?? null;
                if (!in_array($status, ['success', 'abandoned', 'failed', 'reversed', 'ongoing', 'pending', 'processing', 'queued'], true)
                    || ($data['reference'] ?? null) !== $payment->reference
                    || ($data['currency'] ?? null) !== $order->currency
                    || (string) ($data['amount'] ?? '') !== (string) $order->total) {
                    Log::warning('Paystack verification did not match the order.', ['reference' => $payment->reference]);
                    if (!$order->paid_at) {
                        $payment->update(['status' => 'mismatch', 'verified_at' => now()]);
                    }

                    return false;
                }

                if ($status === 'success' && !$order->paid_at) {
                    if (!isset($data['id']) || !preg_match('/^[0-9]{1,32}$/D', (string) $data['id'])) {
                        return false;
                    }
                    $payment->update([
                        'status' => 'paid',
                        'provider_id' => (string) $data['id'],
                        'provider_status' => $status,
                        'channel' => is_string($data['channel'] ?? null) ? substr($data['channel'], 0, 40) : null,
                        'checkout_url' => null,
                        'verified_at' => now(),
                    ]);
                    $order->update(['status' => 'paid', 'paid_at' => now()]);
                    $order->updates()->create(['status' => 'paid']);
                    RestaurantMessage::afterTransaction($order->email, 'Payment received', "We received ".Setting::money($order->total)." for order {$order->number}. Your receipt is available online.", route('orders.show', $order));
                    RestaurantMessage::afterTransaction(Setting::current()['email'], 'New paid order', "Order {$order->number} is ready for staff review.", route('admin.orders.show', $order));
                } elseif (!$order->paid_at) {
                    $payment->update([
                        'status' => in_array($status, ['abandoned', 'failed', 'reversed'], true) ? $status : 'pending',
                        'provider_status' => $status,
                        'verified_at' => now(),
                    ]);
                }

                if ($fingerprint && $status === 'success') {
                    PaymentEvent::create(['payment_id' => $payment->id, 'fingerprint' => $fingerprint, 'type' => 'charge.success']);
                }

                return !$fingerprint || $status === 'success';
            }, 3);
        } catch (UniqueConstraintViolationException $error) {
            // A gateway transaction ID is globally unique and must never pay a second order.
            Log::warning('Paystack transaction or event already recorded.', ['reference' => $payment->reference]);

            return false;
        }
    }

    public function signed(Request $request): bool
    {
        $body = $request->getContent();
        $header = $request->header('Paystack-Signature');
        $secrets = config('restaurant.paystack.webhook_secrets');
        if ($header === null && !$secrets) {
            return $this->configured() && hash_equals(
                hash_hmac('sha512', $body, config('restaurant.paystack.secret_key')),
                (string) $request->header('X-Paystack-Signature')
            );
        }

        $fields = array_map('trim', explode(',', (string) $header));
        $timestamps = array_values(array_filter($fields, fn ($field) => str_starts_with($field, 't=')));
        if (count($timestamps) !== 1 || !preg_match('/^t=([0-9]{1,12})$/D', $timestamps[0], $matches)
            || abs(now()->timestamp - (int) $matches[1]) > 300) {
            return false;
        }
        foreach ($secrets as $secret) {
            $expected = hash_hmac('sha512', $matches[1].'.'.$body, $secret);
            foreach ($fields as $field) {
                if (preg_match('/^v1=whk_[A-Za-z0-9_-]+:([a-f0-9]{128})$/D', $field, $signature)
                    && hash_equals($expected, $signature[1])) {
                    return true;
                }
            }
        }

        return false;
    }
}


