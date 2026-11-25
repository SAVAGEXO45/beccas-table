<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Order, Payment, Reservation, User};
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'Verified sales' => \App\Models\Setting::money((int) Order::whereNotNull('paid_at')->sum('total')),
            'Orders' => Order::count(),
            'Pending payment' => Order::where('status', 'pending_payment')->count(),
            'Paid, awaiting confirmation' => Order::where('status', 'paid')->count(),
            'Completed orders' => Order::where('status', 'completed')->count(),
            'Customers' => User::where('is_admin', false)->count(),
            'Pending reservations' => Reservation::where('status', 'pending')->count(),
            'Approved reservations' => Reservation::where('status', 'approved')->count(),
        ];

        return view('admin.dashboard', [
            'stats' => $stats, 'orders' => Order::with('payment')->latest()->limit(6)->get(),
            'payments' => Payment::with('order')->latest('updated_at')->limit(6)->get(),
        ]);
    }

    public function customers(Request $request)
    {
        $search = $request->validate(['q' => 'nullable|string|max:100'])['q'] ?? '';
        $customers = User::where('is_admin', false)
            ->when($search !== '', fn ($query) => $query->where(fn ($match) => $match->whereLike('name', '%'.$search.'%')->orWhereLike('email', '%'.$search.'%')))
            ->withCount('orders')->latest()->paginate(20)->withQueryString();

        return view('admin.customers', compact('customers', 'search'));
    }

    public function customer(User $customer)
    {
        abort_if($customer->is_admin, 404);
        $orders = $customer->orders()->with('payment')->latest()->paginate(20);

        return view('admin.customer', compact('customer', 'orders'));
    }

    public function updateCustomer(Request $request, User $customer)
    {
        abort_if($customer->is_admin, 404);
        $request->validate(['disabled' => 'required|boolean']);
        $customer->forceFill(['is_disabled' => $request->boolean('disabled'), 'remember_token' => null])->save();

        if ($customer->is_disabled) {
            \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $customer->id)->delete();
        }

        return back()->with('status', 'Customer account updated.');
    }
}

