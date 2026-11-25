<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Photo, Setting};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    public function edit()
    {
        return view('admin.settings', ['photos' => Photo::orderBy('alt')->get()]);
    }

    public function bank()
    {
        return view('admin.bank-settings', ['bank' => Setting::current()['bank_transfer']]);
    }

    public function updateBank(Request $request)
    {
        $data = $request->validate([
            'bank_name' => 'required|string|max:120',
            'account_name' => 'required|string|max:160',
            'account_number' => 'required|digits:10',
            'instructions' => 'required|string|max:1000',
            'enabled' => 'required|boolean',
        ]);
        $data['enabled'] = $request->boolean('enabled');
        DB::transaction(function () use ($data) {
            $settings = Setting::lockForUpdate()->findOrFail(1);
            $settings->update(['content' => array_replace($settings->values(), ['bank_transfer' => $data])]);
        }, 3);

        return back()->with('status', 'Bank transfer settings saved.');
    }

    public function update(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:100', 'tagline' => 'required|string|max:180',
            'address' => 'required|string|max:220', 'email' => 'required|email|max:254',
            'phone' => ['required', 'regex:/^[+0-9 ()-]{7,30}$/'],
            'instagram' => 'required|url:https|max:250', 'facebook' => 'required|url:https|max:250', 'whatsapp' => 'required|url:https|max:250',
            'instagram_label' => 'required|string|max:100', 'facebook_label' => 'required|string|max:100',
            'intro_heading' => 'required|string|max:180', 'about_heading' => 'required|string|max:180',
            'intro' => 'required|string|max:1000', 'about' => 'required|string|max:5000',
            'reasons' => 'required|string|max:1000', 'footer' => 'required|string|max:300',
            'pickup' => 'required|string|max:300', 'delivery_enabled' => 'nullable|boolean',
            'delivery_areas' => 'nullable|string|max:6000',
            'minimum' => ['required', 'regex:/^\d{1,7}(\.\d{1,2})?$/D'],
            'capacity' => 'nullable|integer|min:1|max:500',
            'duration' => 'required|integer|min:15|max:360',
            'lead_minutes' => 'required|integer|min:0|max:1440',
            'max_days' => 'required|integer|min:1|max:365',
            'closed_dates' => 'nullable|string|max:5000',
            'reservations_enabled' => 'nullable|boolean',
            'privacy' => 'nullable|string|max:20000', 'terms' => 'nullable|string|max:20000', 'faq' => 'nullable|string|max:10000',
        ];
        foreach (['charcoal', 'gold', 'ivory'] as $field) {
            $rules[$field] = ['required', 'regex:/^#[0-9a-fA-F]{6}$/D'];
        }
        foreach (['hero_photo_id', 'about_photo_id', 'logo_photo_id'] as $field) {
            $rules[$field] = 'nullable|integer|exists:photos,id';
        }
        $rules['hours'] = 'required|array:'.implode(',', Setting::DAYS);
        foreach (Setting::DAYS as $day) {
            $rules["hours.$day"] = 'required|array:open,close,closed';
            $rules["hours.$day.open"] = 'required|date_format:H:i';
            $rules["hours.$day.close"] = 'required|date_format:H:i';
            $rules["hours.$day.closed"] = 'nullable|boolean';
        }

        $data = $request->validate($rules);
        foreach ($data['hours'] as $day => &$hours) {
            $hours['closed'] = (bool) ($hours['closed'] ?? false);
            if (!$hours['closed'] && $hours['open'] >= $hours['close']) {
                throw ValidationException::withMessages(["hours.$day.close" => "$day closing time must follow opening time."]);
            }
        }
        unset($hours);
        $areas = [];
        foreach (preg_split('/\R/', $data['delivery_areas'] ?? '') as $line) {
            if (trim($line) === '') {
                continue;
            }
            [$area, $price] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            if ($area === '' || mb_strlen($area) > 100 || isset($areas[$area]) || !preg_match('/^\d{1,7}(\.\d{1,2})?$/D', $price)) {
                throw ValidationException::withMessages(['delivery_areas' => 'Use one unique area per line in the format Area | 1500.00.']);
            }
            $areas[$area] = Setting::minor($price);
        }
        $data['delivery_areas'] = $areas;
        $data['minimum'] = Setting::minor($data['minimum']);
        $data['delivery_enabled'] = $request->boolean('delivery_enabled');
        $data['reservations_enabled'] = $request->boolean('reservations_enabled');
        if ($data['delivery_enabled'] && !$areas) {
            throw ValidationException::withMessages(['delivery_areas' => 'Add at least one delivery area before enabling delivery.']);
        }

        $dates = preg_split('/[\s,]+/', trim($data['closed_dates'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($dates as $date) {
            if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $parts) || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
                throw ValidationException::withMessages(['closed_dates' => 'Enter closure dates as YYYY-MM-DD.']);
            }
        }
        $data['closed_dates'] = array_values(array_unique($dates));
        DB::transaction(function () use ($data) {
            $setting = Setting::lockForUpdate()->findOrFail(1);
            $setting->update(['content' => array_replace($setting->values(), $data)]);
        }, 3);

        return back()->with('status', 'Business settings updated.');
    }
}


