<?php

namespace App\Http\Controllers;

use App\Mail\RestaurantMessage;
use App\Models\{Reservation, Setting};
use App\Services\Tables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReservationController extends Controller
{
    public function create()
    {
        return view('reservations.create');
    }

    public function store(Request $request, Tables $tables)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'phone' => ['required', 'regex:/^[+0-9 ()-]{7,30}$/'],
            'email' => 'required|email|max:254',
            'date' => 'required|date_format:Y-m-d',
            'time' => 'required|date_format:H:i',
            'guests' => 'required|integer|min:1|max:100',
            'requests' => 'nullable|string|max:1000',
        ]);

        $reservation = DB::transaction(function () use ($data, $tables) {
            $setting = Setting::lockForUpdate()->findOrFail(1)->values();
            $end = $tables->assertAvailable($setting, $data['date'], $data['time'], (int) $data['guests']);
            $data['time'] .= ':00';
            $reservation = Reservation::create($data + ['code' => 'RES-'.strtoupper(Str::random(12)), 'status' => 'pending', 'ends_at' => $end->toDateTimeString()]);
            RestaurantMessage::sendTo($reservation->email, 'Reservation request received', "Your request {$reservation->code} for {$reservation->date} at {$reservation->time} is awaiting confirmation.");
            RestaurantMessage::sendTo($setting['email'], 'New reservation request', "Request {$reservation->code} is awaiting review.", route('admin.reservations.index'));

            return $reservation;
        }, 3);

        $request->session()->put('reservation_code', $reservation->code);
        return redirect()->route('reservations.confirmation');
    }

    public function confirmation(Request $request)
    {
        $code = $request->session()->get('reservation_code');
        abort_unless($code, 404);
        $reservation = Reservation::where('code', $code)->firstOrFail();

        return view('reservations.confirmation', compact('reservation'));
    }
}


