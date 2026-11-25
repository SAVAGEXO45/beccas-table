<?php

namespace App\Http\Controllers;

use App\Mail\RestaurantMessage;
use App\Models\{Order, Payment, Setting};
use App\Services\{BankTransfer, Cart, Paystack};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache, DB, Gate};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function cart(Cart $cart)
    {
        return view('orders.cart', ['lines' => $cart->lines()]);
    }

    public function updateCart(Request $request, Cart $cart, int $meal)
    {
        $data = $request->validate([
            'quantity' => 'required|integer|min:0|max:30',
            'variation' => 'nullable|integer|min:1',
            'addons' => 'sometimes|array|max:12',
            'addons.*' => 'integer|min:1|distinct',
            'line' => 'nullable|string|max:95|regex:/^[0-9]+(?:-[a-f0-9]{64})?$/D',
            'return_to_checkout' => 'sometimes|boolean',
        ]);
        $cart->set(
            $meal, (int) $data['quantity'], $request->boolean('add'),
            isset($data['variation']) ? (int) $data['variation'] : null, $data['addons'] ?? [], $data['line'] ?? null,
        );

        $destination = ($data['return_to_checkout'] ?? false) && $request->session()->has('cart') && $request->session()->get('cart')
            ? 'checkout' : 'cart';

        return redirect()->route($destination)->with('status', 'Cart updated.');
    }

    public function checkout(Request $request, Cart $cart, Paystack $paystack)
    {
        $lines = $cart->lines();
        if ($lines->isEmpty() || $lines->contains('available', false)) {
            return redirect()->route('cart')->withErrors(['cart' => 'Check your cart before continuing to checkout.']);
        }

        $quote = $cart->quote($lines, Setting::current());
        $reference = $request->session()->get('checkout_quote') === $quote
            ? $request->session()->get('checkout_reference')
            : 'BT-'.Str::uuid();
        $request->session()->put(['checkout_reference' => $reference, 'checkout_quote' => $quote]);

        return view('orders.checkout', compact('lines', 'reference') + [
            'paystackReady' => $paystack->configured(),
            'bank' => Setting::current()['bank_transfer'],
        ]);
    }

    public function place(Request $request, Cart $cart, Paystack $paystack)
    {
        $data = $request->validate([
            'checkout_reference' => 'required|string|max:80',
            'name' => 'required|string|max:100',
            'phone' => ['required', 'regex:/^[+0-9 ()-]{7,30}$/'],
            'fulfilment' => 'required|in:pickup,delivery',
            'delivery_area' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500|required_if:fulfilment,delivery',
            'receipt_email' => 'nullable|email|max:254',
            'delivery_instructions' => 'nullable|string|max:1000',
            'payment_method' => 'sometimes|required|in:paystack,bank_transfer',
        ]);
        $existing = Payment::where('reference', $data['checkout_reference'])->first();
        if ($existing) {
            abort_unless($existing->order->user_id === $request->user()->id, 403);

            return redirect()->route('orders.show', $existing->order);
        }
        abort_unless($data['checkout_reference'] === $request->session()->get('checkout_reference'), 419);
        $method = $data['payment_method'] ?? 'paystack';
        if ($method === 'paystack' && !$paystack->configured()) {
            return back()->withErrors(['payment' => 'Online payment is currently unavailable. Please contact the restaurant.'])->withInput();
        }

        $order = DB::transaction(function () use ($request, $cart, $data, $method) {
            $business = Setting::lockForUpdate()->findOrFail(1)->values();
            if ($method === 'bank_transfer' && !$business['bank_transfer']['enabled']) {
                throw ValidationException::withMessages(['payment' => 'Bank transfer is currently unavailable.']);
            }
            $lines = $cart->lines(true);
            if ($lines->isEmpty() || $lines->contains('available', false)) {
                throw ValidationException::withMessages(['cart' => 'A meal in your cart is unavailable. Please review your cart.']);
            }
            if ($cart->quote($lines, $business) !== $request->session()->get('checkout_quote')) {
                throw ValidationException::withMessages(['cart' => 'Your prices or fulfilment options changed. Review the updated checkout totals.'])->redirectTo(route('checkout'));
            }
            $subtotal = $lines->sum('total');
            if ($subtotal < $business['minimum']) {
                throw ValidationException::withMessages(['cart' => 'The minimum subtotal is '.Setting::money($business['minimum']).'.']);
            }
            $delivery = $data['fulfilment'] === 'delivery';
            $area = $delivery ? ($data['delivery_area'] ?? '') : null;
            if ($delivery && (!$business['delivery_enabled'] || !array_key_exists($area, $business['delivery_areas']))) {
                throw ValidationException::withMessages(['delivery_area' => 'Choose an available delivery area.']);
            }
            $fee = $delivery ? $business['delivery_areas'][$area] : 0;
            if ($method === 'paystack' && $subtotal + $fee < Paystack::NGN_MINIMUM) {
                throw ValidationException::withMessages(['cart' => 'Paystack checkout requires a total of at least '.Setting::money(Paystack::NGN_MINIMUM).'.']);
            }
            $order = $request->user()->orders()->create([
                'number' => 'BT-'.strtoupper(Str::random(12)),
                'status' => 'pending_payment',
                'name' => $data['name'],
                'email' => $data['receipt_email'] ?? $request->user()->email,
                'phone' => $data['phone'],
                'fulfilment' => $data['fulfilment'],
                'address' => $delivery ? $data['address'] : null,
                'delivery_area' => $area,
                'delivery_instructions' => $delivery ? ($data['delivery_instructions'] ?? null) : null,
                'subtotal' => $subtotal,
                'delivery_fee' => $fee,
                'total' => $subtotal + $fee,
                'currency' => $business['currency'],
            ]);
            foreach ($lines as $line) {
                $order->items()->create([
                    'meal_id' => $line['id'], 'name' => $line['meal']->name,
                    'unit_price' => $line['unit_price'], 'quantity' => $line['quantity'], 'options' => $line['options'],
                ]);
            }
            $order->updates()->create(['status' => 'pending_payment']);
            $bank = $method === 'bank_transfer' ? $business['bank_transfer'] : null;
            $order->payment()->create([
                'reference' => $data['checkout_reference'],
                'method' => $method,
                'status' => $bank ? 'pending' : 'created',
                'bank_details' => $bank,
            ]);
            RestaurantMessage::afterTransaction(
                $order->email, 'Order placed',
                $bank
                    ? "Order {$order->number} is awaiting a transfer of ".Setting::money($order->total)." to {$bank['bank_name']}, {$bank['account_name']}, {$bank['account_number']}. {$bank['instructions']} Report the transfer on your order page."
                    : "Order {$order->number} is awaiting payment.",
                route('orders.show', $order)
            );

            return $order;
        }, 3);

        $request->session()->forget(['cart', 'checkout_reference', 'checkout_quote']);

        return $method === 'bank_transfer'
            ? redirect()->route('orders.show', $order)->with('status', 'Order received. Follow the bank transfer instructions below.')
            : $this->openCheckout($order, $paystack);
    }

    public function show(Order $order)
    {
        Gate::authorize('view', $order);
        return view('orders.show', ['order' => $order->load(['items', 'payment', 'updates']), 'staff' => false]);
    }

    public function notifyTransfer(Request $request, Order $order, BankTransfer $transfer)
    {
        Gate::authorize('view', $order);
        $transfer->notify($order, $request->user());

        return back()->with('status', 'Transfer reported. The restaurant will verify receipt before preparing your order.');
    }

    public function pay(Order $order, Paystack $paystack)
    {
        Gate::authorize('view', $order);
        abort_unless($order->user_id === auth()->id(), 403);

        return $this->openCheckout($order, $paystack);
    }

    private function openCheckout(Order $order, Paystack $paystack)
    {
        $payment = $order->payment;
        abort_unless($payment?->method === 'paystack', 404);
        $url = Cache::lock('paystack-init:'.$payment->id, 15)->get(fn () => $paystack->start($payment->fresh()));

        return $url ? redirect()->away($url) : redirect()->route('orders.show', $order)
            ->withErrors(['payment' => 'Checkout is unavailable for this payment. Check the payment status or contact the restaurant.']);
    }

    public function refresh(Order $order, Paystack $paystack)
    {
        Gate::authorize('view', $order);
        abort_unless($order->payment?->method === 'paystack', 404);
        $verified = $paystack->verify($order->payment);

        return back()->with('status', $verified ? 'Payment status updated.' : 'Payment could not be verified yet.');
    }

    public function callback(Request $request, Paystack $paystack)
    {
        $reference = $request->validate(['reference' => 'required|string|max:80'])['reference'];
        $payment = Payment::where('method', 'paystack')->where('reference', $reference)->firstOrFail();
        Gate::authorize('view', $payment->order);
        $verified = $paystack->verify($payment);

        return redirect()->route('orders.show', $payment->order)
            ->with('status', $verified ? 'Payment status checked.' : 'We could not confirm payment yet. You can check again on this page.');
    }

    public function webhook(Request $request, Paystack $paystack)
    {
        abort_unless(strlen($request->getContent()) <= 262144 && $paystack->signed($request), 401);
        $event = json_decode($request->getContent(), true);
        $reference = data_get($event, 'data.reference');
        if (data_get($event, 'event') !== 'charge.success' || !is_string($reference) || strlen($reference) > 80) {
            return response('OK');
        }

        $payment = Payment::where('method', 'paystack')->where('reference', $reference)->first();
        if ($payment && !$paystack->verify($payment, hash('sha256', 'charge.success|'.$reference))) {
            return response('Verification pending', 503);
        }

        return response('OK');
    }
}



