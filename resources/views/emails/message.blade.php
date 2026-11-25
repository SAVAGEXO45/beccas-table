<!doctype html>
<html lang="en-NG">
<head><meta charset="utf-8"><title>{{ $title }}</title></head>
<body style="background:{{ $business['ivory'] }};color:{{ $business['charcoal'] }};font:16px Arial,sans-serif;padding:24px">
    <div style="margin:auto;max-width:560px;border-top:5px solid {{ $business['gold'] }};padding:24px;background:#fff">
        <p style="font:24px Georgia,serif">{{ $business['name'] }}</p>
        <h1 style="font:32px Georgia,serif">{{ $title }}</h1>
        <p>{{ $bodyText }}</p>
        @if($link)<p><a href="{{ $link }}" style="color:{{ $business['charcoal'] }}">View details</a></p>@endif
        <p>{{ $business['address'] }}<br>{{ $business['phone'] }}</p>
    </div>
</body>
</html>

