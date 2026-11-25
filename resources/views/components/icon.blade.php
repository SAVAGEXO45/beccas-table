@props(['name'])
@php
    $paths = [
        'overview' => 'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z',
        'orders' => 'M6 4h12v17l-3-2-3 2-3-2-3 2V4zm3 5h6m-6 4h6',
        'payments' => 'M3 5h18v14H3zM3 10h18m-14 5h4',
        'reservations' => 'M4 6h16v15H4zM8 3v6m8-6v6M4 11h16m-12 5h3',
        'customers' => 'M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2m16 0v-2a4 4 0 0 0-2-3.5M14 8a4 4 0 1 1-8 0 4 4 0 0 1 8 0zm3-4a4 4 0 0 1 0 8',
        'meals' => 'M3 13h18m-17 0a8 8 0 0 1 16 0M12 5V3m-8 15h16',
        'categories' => 'M9 6h11M9 12h11M9 18h11M4 6h1m-1 6h1m-1 6h1',
        'photos' => 'M3 4h18v16H3zM3 17l5-5 4 4 3-3 6 6M16 8h.01',
        'testimonials' => 'M4 5h16v11H9l-5 4V5zm4 4h8m-8 3h6',
        'settings' => 'M4 6h16M4 12h16M4 18h16M8 4v4m8 2v4m-7 2v4',
        'password' => 'M5 11h14v10H5zM8 11V7a4 4 0 0 1 8 0v4m-4 4v2',
        'history' => 'M3 12a9 9 0 1 1 2.7 6.4M3 5v7h7m2-5v5l4 2',
        'logout' => 'M10 3H4v18h6m-1-9h12m-4-4 4 4-4 4',
        'bank' => 'M3 9h18L12 3zM5 11v7m5-7v7m4-7v7m5-7v7M3 21h18',
        'copy' => 'M8 8h12v13H8zM16 5V3H4v14h2',
        'wallet' => 'M20 8V5H4v14h16v-4m-2-7h4v7h-4a3.5 3.5 0 0 1 0-7zm.5 3.5h.01',
        'quantity' => 'M9 3 7 21M17 3l-2 18M4 9h17M3 15h17',
        'pin' => 'M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0zm-5 0a2 2 0 1 0-4 0 2 2 0 0 0 4 0z',
        'clock' => 'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0zm-9-5v5l3 2',
        'circle-check' => 'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0zm-13 0 3 3 5-6',
        'shield-check' => 'M12 22s8-4 8-11V6l-8-3-8 3v5c0 7 8 11 8 11zm-4-11 3 3 5-5',
        'pot' => 'M7 8c0-2 2-3 5-3s5 1 5 3M12 5V3M3 9h18m-16 0v8a3 3 0 0 0 3 3h8a3 3 0 0 0 3-3V9M2 13h3m14 0h3',
        'package-check' => 'M13 21H4V4h16v9M8 4v6h8V4m-3 13 3 3 5-5',
        'truck' => 'M3 5h12v12H3zm12 5h4l3 3v4h-7M8 17a2 2 0 1 1-4 0m16 0a2 2 0 1 1-4 0',
        'circle-x' => 'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0zm-12-3 6 6m0-6-6 6',
        'warning' => 'M12 3 2 20h20zM12 9v4m0 3h.01',
        'arrow-right' => 'M4 12h16m-6-6 6 6-6 6',
    ];
@endphp
<svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    <path d="{{ $paths[$name] }}"/>
</svg>
