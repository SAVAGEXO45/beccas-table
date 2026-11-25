<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\RestaurantMessage;
use App\Models\{Order, Payment, Reservation, Setting};
use App\Services\{BankTransfer, Tables};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OperationsController extends Controller
{
    public function orders(Request $request)
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => ['nullable', Rule::in(Order::STATUSES)],
            'method' => ['nullable', Rule::in(array_keys(Payment::METHODS))],
            'payment_status' => ['nullable', Rule::in(Payment::STATUSES)],
        ]);
        $search = $filters['q'] ?? '';
        $query = Order::with('payment')->latest()
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(fn ($match) => $match->whereLike('number', '%'.$search.'%')->orWhereLike('name', '%'.$search.'%')->orWhereLike('email', '%'.$search.'%')));

        if (!empty($filters['method']) || !empty($filters['payment_status'])) {
            $query->whereHas('payment', function ($payments) use ($filters) {
                $payments->when($filters['method'] ?? null, fn ($q, $method) => $q->where('method', $method))
                    ->when($filters['payment_status'] ?? null, fn ($q, $status) => $q->whereIn('status', $status === 'pending' ? ['created', 'pending'] : [$status]));
            });
        }

        return view('admin.orders', ['orders' => $query->paginate(20)->withQueryString(), 'search' => $search]);
    }

    public function order(Order $order)
    {
        return view('orders.show', ['order' => $order->load(['items', 'payment.reviewer', 'updates']), 'staff' => true]);
    }

    public function updateOrder(Request $request, Order $order)
    {
        $next = $request->validate(['status' => ['required', Rule::in(Order::STATUSES)]])['status'];
        DB::transaction(function () use ($order, $next) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            if (!in_array($next, $order->transitions(), true)) {
                throw ValidationException::withMessages(['status' => 'That status change is unavailable for this order.']);
            }
            $order->update(['status' => $next]);
            $order->updates()->create(['status' => $next]);
            RestaurantMessage::afterTransaction($order->email, 'Order '.strtolower($order->progressLabel($next)), "Order {$order->number} is now ".strtolower($order->progressLabel($next)).'.', route('orders.show', $order));
        }, 3);

        return back()->with('status', 'Order updated.');
    }

    public function reviewTransfer(Request $request, Order $order, BankTransfer $transfer)
    {
        $data = $request->validate([
            'decision' => 'required|in:confirm,reject',
            'bank_transaction_reference' => ['exclude_unless:decision,confirm', 'required', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/D'],
            'receipt_checked' => 'exclude_unless:decision,confirm|accepted',
            'rejection_reason' => 'exclude_unless:decision,reject|required|string|max:600',
        ]);
        $transfer->review($order, $request->user(), $data);

        return back()->with('status', $data['decision'] === 'confirm' ? 'Bank transfer confirmed.' : 'Bank transfer rejected.');
    }

    public function payments(Request $request)
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:100',
            'method' => ['nullable', Rule::in(array_keys(Payment::METHODS))],
            'payment_status' => ['nullable', Rule::in(Payment::STATUSES)],
            'needs_review' => 'nullable|boolean',
        ]);
        $search = trim($filters['q'] ?? '');
        $payments = Payment::with('order')->latest('updated_at')->orderByDesc('id')
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->whereLike('reference', '%'.$search.'%')->orWhereLike('provider_id', '%'.$search.'%')
                ->orWhereLike('bank_transaction_reference', '%'.$search.'%')
                ->orWhereHas('order', fn ($order) => $order->whereLike('number', '%'.$search.'%'))))
            ->when($filters['method'] ?? null, fn ($q, $method) => $q->where('method', $method))
            ->when($filters['payment_status'] ?? null, fn ($q, $status) => $q->whereIn('status', $status === 'pending' ? ['created', 'pending'] : [$status]))
            ->when($filters['needs_review'] ?? false, fn ($q) => $q->where('method', 'bank_transfer')
                ->where('status', 'pending')->whereNotNull('transfer_notified_at'))
            ->paginate(20)->withQueryString();

        return view('admin.payments', compact('payments', 'search'));
    }

    public function reservations(Request $request)
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => ['nullable', Rule::in(Reservation::STATUSES)],
            'date' => 'nullable|date_format:Y-m-d',
        ]);
        $search = $filters['q'] ?? '';
        $reservations = Reservation::orderByDesc('date')->orderBy('time')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['date'] ?? null, fn ($query, $date) => $query->where('date', $date))
            ->when($search !== '', fn ($query) => $query->where(fn ($match) => $match->whereLike('code', '%'.$search.'%')->orWhereLike('name', '%'.$search.'%')->orWhereLike('email', '%'.$search.'%')))
            ->paginate(20)->withQueryString();

        return view('admin.reservations', compact('reservations', 'search'));
    }

    public function updateReservation(Request $request, Reservation $reservation, Tables $tables)
    {
        $status = $request->validate(['status' => 'required|in:approved,rejected,cancelled'])['status'];
        DB::transaction(function () use ($reservation, $status, $tables) {
            $settings = Setting::lockForUpdate()->findOrFail(1)->values();
            $reservation = Reservation::lockForUpdate()->findOrFail($reservation->id);
            $allowed = match ($reservation->status) {
                'pending' => ['approved', 'rejected', 'cancelled'],
                'approved' => ['cancelled'],
                default => [],
            };
            if (!in_array($status, $allowed, true)) {
                throw ValidationException::withMessages(['status' => 'That status change is unavailable for this reservation.']);
            }
            if ($status === 'approved') {
                $tables->assertAvailable($settings, $reservation->date, $reservation->time, $reservation->guests, $reservation);
            }

            $reservation->update(['status' => $status]);
            RestaurantMessage::sendTo($reservation->email, 'Reservation '.$status, "Your reservation {$reservation->code} for {$reservation->date} at {$reservation->time} has been {$status}.", route('contact'));
        }, 3);

        return back()->with('status', 'Reservation updated.');
    }
}

