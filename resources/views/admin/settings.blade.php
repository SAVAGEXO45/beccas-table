@php
    $business = \App\Models\Setting::current();
    $areaLines = collect($business['delivery_areas'])->map(fn ($fee, $area) => $area.' | '.number_format($fee / 100, 2, '.', ''))->implode("\n");
@endphp
<x-layout title="Website settings" :admin="true">
    <header class="page-header"><h1>Website settings</h1></header>
    <form class="form-stack" method="POST" action="{{ route('admin.settings.update') }}">
        @csrf @method('PATCH')
        <section class="panel form-stack">
            <h2>Business and branding</h2>
            <div class="form-row">
                <x-field name="name" label="Business name" :value="$business['name']" maxlength="100" required/>
                <x-field name="tagline" label="Tagline" :value="$business['tagline']" maxlength="180" required/>
            </div>
            <x-field name="address" label="Address" :value="$business['address']" maxlength="220" required/>
            <div class="form-row">
                <x-field name="phone" type="tel" label="Phone" :value="$business['phone']" maxlength="30" required/>
                <x-field name="email" type="email" label="Business email" :value="$business['email']" maxlength="254" required/>
            </div>
            <div class="form-row">
                <x-field name="instagram" type="url" label="Instagram URL" :value="$business['instagram']" maxlength="250" required/>
                <x-field name="instagram_label" label="Instagram handle" :value="$business['instagram_label']" maxlength="100" required/>
            </div>
            <div class="form-row">
                <x-field name="facebook" type="url" label="Facebook URL" :value="$business['facebook']" maxlength="250" required/>
                <x-field name="facebook_label" label="Facebook page name" :value="$business['facebook_label']" maxlength="100" required/>
            </div>
            <x-field name="whatsapp" type="url" label="WhatsApp URL" :value="$business['whatsapp']" maxlength="250" required/>
            <div class="form-row">
                @foreach(['charcoal' => 'Deep charcoal', 'gold' => 'Champagne gold', 'ivory' => 'Warm ivory'] as $field => $label)<x-field :name="$field" :label="$label" type="color" :value="$business[$field]" required/>@endforeach
            </div>
            <p>Currency {{ $business['currency'] }}</p>
            <div class="form-row">
            @foreach(['logo_photo_id' => 'Brand image', 'hero_photo_id' => 'Homepage hero photo', 'about_photo_id' => 'About photo'] as $field => $label)
                <label>{{ $label }}<select name="{{ $field }}"><option value="">No image</option>@foreach($photos as $photo)<option value="{{ $photo->id }}" @selected(old($field, $business[$field]) == $photo->id)>{{ $photo->alt }}</option>@endforeach</select></label>
            @endforeach
            </div>
        </section>
        <section class="panel form-stack">
            <h2>Website content</h2>
            @foreach(['intro_heading' => 'Homepage introduction heading', 'about_heading' => 'About heading'] as $field => $label)
                <x-field :name="$field" :label="$label" :value="$business[$field]" maxlength="180" required/>
            @endforeach
            @foreach(['intro' => ['Homepage introduction', 1000], 'about' => ['Restaurant story', 5000], 'reasons' => ['What we offer', 1000], 'footer' => ['Footer description', 300], 'faq' => ['Custom FAQ', 10000], 'privacy' => ['Privacy policy', 20000], 'terms' => ['Terms and conditions', 20000]] as $field => [$label, $max])
                <x-field :name="$field" :label="$label" :value="$business[$field]" rows="4" :maxlength="$max" :required="in_array($field, ['intro', 'about', 'reasons', 'footer'], true)"/>
            @endforeach
        </section>
        <section class="panel form-stack">
            <h2>Pickup and delivery</h2>
            <x-field name="pickup" label="Pickup instructions" :value="$business['pickup']" maxlength="300" required/>
            <x-field name="minimum" label="Minimum subtotal (NGN)" type="number" :value="number_format($business['minimum'] / 100, 2, '.', '')" min="0" max="9999999.99" step="0.01" required/>
            <input type="hidden" name="delivery_enabled" value="0">
            <label class="check"><input type="checkbox" name="delivery_enabled" value="1" @checked(old('delivery_enabled', $business['delivery_enabled']))> Enable delivery</label>
            <x-field name="delivery_areas" label="Delivery areas and fees (one per line, Area | Fee in NGN)" :value="$areaLines" rows="4" maxlength="6000"/>
        </section>
        <section class="panel form-stack">
            <h2>Opening hours and reservations</h2>
            <input type="hidden" name="reservations_enabled" value="0">
            <label class="check"><input type="checkbox" name="reservations_enabled" value="1" @checked(old('reservations_enabled', $business['reservations_enabled']))> Accept online reservation requests</label>
            <div class="form-row">
                <x-field name="capacity" label="Guest capacity per time window" type="number" :value="$business['capacity']" min="1" max="500"/>
                <x-field name="duration" label="Reservation duration (minutes)" type="number" :value="$business['duration']" min="15" max="360" required/>
            </div>
            <p>With capacity blank, the system accepts one overlapping request.</p>
            <div class="form-row">
                <x-field name="lead_minutes" label="Minimum lead time (minutes)" type="number" :value="$business['lead_minutes']" min="0" max="1440" required/>
                <x-field name="max_days" label="Booking window (days)" type="number" :value="$business['max_days']" min="1" max="365" required/>
            </div>
            <x-field name="closed_dates" label="Closure dates (YYYY-MM-DD, one per line)" :value="implode(chr(10), $business['closed_dates'])" rows="3" maxlength="5000"/>
            @foreach($business['hours'] as $day => $period)
                <fieldset><legend>{{ $day }}</legend><div class="form-row">
                    <label>Opens<input type="time" name="hours[{{ $day }}][open]" value="{{ old('hours.'.$day.'.open', $period['open']) }}" required></label>
                    <label>Closes<input type="time" name="hours[{{ $day }}][close]" value="{{ old('hours.'.$day.'.close', $period['close']) }}" required></label>
                    <input type="hidden" name="hours[{{ $day }}][closed]" value="0">
                    <label class="check"><input type="checkbox" name="hours[{{ $day }}][closed]" value="1" @checked(old('hours.'.$day.'.closed', $period['closed']))> Closed</label>
                </div></fieldset>
            @endforeach
        </section>
        <button class="button">Save all settings</button>
    </form>
</x-layout>


