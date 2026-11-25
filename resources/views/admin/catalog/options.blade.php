<x-layout title="Meal options" :admin="true">
    <header class="page-header"><a class="text-link" href="{{ route('admin.catalog.edit', ['meals', $meal->id]) }}">Back to meal</a><h1>Meal options</h1><p>{{ $meal->name }}</p></header>
    @unless($meal->price_confirmed)<x-menu-price-notice/>@endunless
    <form class="panel form-stack" method="POST" action="{{ route('admin.menu.meals.save-options', $meal) }}" data-menu-options>
        @csrf @method('PUT')
        @foreach(['variations' => 'Variations', 'addons' => 'Optional extras'] as $type => $heading)
            <section class="form-stack" data-option-section="{{ $type }}">
                <h2>{{ $heading }}</h2>
                <p>{{ $type === 'variations' ? 'Give each offered size and protein combination its full meal price. A customer selects one variation.' : 'Enter the additional price per portion for each optional extra.' }}</p>
                <div class="form-stack" data-option-rows>
                    @foreach($meal->{$type} as $item)
                        <x-admin.menu-option-row :type="$type" :index="$loop->index" :item="$item"/>
                    @endforeach
                </div>
                <template data-option-template><x-admin.menu-option-row :type="$type" index="__index__"/></template>
                <button class="button outline" type="button" data-add-option>{{ $type === 'variations' ? 'Add variation' : 'Add extra' }}</button>
            </section>
        @endforeach
        <p>Confirm meal prices again after changing a price or adding or removing an option.</p>
        <button class="button">Save options</button>
    </form>
</x-layout>
