@php
    $groups = [
        'Operations' => [
            ['Orders', 'admin.orders.index', 'orders'],
            ['Payments', 'admin.payments.index', 'payments'],
            ['Reservations', 'admin.reservations.index', 'reservations'],
            ['Customers', 'admin.customers.index', 'customers'],
        ],
        'Catalog' => [
            ['Meals', 'admin.catalog.index', 'meals'],
            ['Categories', 'admin.catalog.index', 'categories'],
            ['Photos', 'admin.catalog.index', 'photos'],
            ['Testimonials', 'admin.catalog.index', 'testimonials'],
        ],
        'Settings' => [
            ['Website settings', 'admin.settings.edit', 'settings'],
            ['Bank transfer settings', 'admin.settings.bank', 'payments'],
            ['Change password', 'admin.password.edit', 'password'],
        ],
    ];
    $catalogSection = request()->route('section');
    if (request()->routeIs('admin.menu.meals.*', 'admin.catalog.category-meals')) {
        $catalogSection = 'meals';
    }
@endphp

<aside id="staff-navigation" class="staff-sidebar" aria-label="Restaurant management">
    <nav class="staff-nav" aria-label="Administration">
        <p class="eyebrow">Restaurant management</p>
        <a href="{{ route('admin.dashboard') }}"@if(request()->routeIs('admin.dashboard')) aria-current="page"@endif>
            <x-staff-icon name="overview"/> <span>Overview</span>
        </a>
        @foreach($groups as $heading => $items)
            <div class="staff-nav-group">
                <h2>{{ $heading }}</h2>
                @foreach($items as [$label, $route, $key])
                    @php
                        $isCatalog = $route === 'admin.catalog.index';
                        $current = match ($route) {
                            'admin.catalog.index' => $catalogSection === $key,
                            'admin.settings.edit', 'admin.settings.bank' => request()->routeIs($route),
                            default => request()->routeIs('admin.'.$key.'.*'),
                        };
                    @endphp
                    <a href="{{ route($route, $isCatalog ? ['section' => $key] : []) }}"@if($current) aria-current="page"@endif>
                        <x-staff-icon :name="$key"/> <span>{{ $label }}</span>
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>
</aside>
