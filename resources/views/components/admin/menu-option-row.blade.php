@props(['type', 'index', 'item' => null])
@php($prefix = $type.'['.$index.']')
<fieldset data-option-row>
    <legend>{{ $item?->name ?: ($type === 'variations' ? 'New variation' : 'New extra') }}</legend>
    @if($item)<input type="hidden" name="{{ $prefix }}[id]" value="{{ $item->id }}">@endif
    <div class="form-row">
        <label>Name<input name="{{ $prefix }}[name]" value="{{ $item?->name }}" maxlength="100" required></label>
        @if($type === 'variations')
            <label>Size<input name="{{ $prefix }}[size]" value="{{ $item?->size }}" maxlength="50" placeholder="Medium"></label>
            <label>Protein<input name="{{ $prefix }}[protein]" value="{{ $item?->protein }}" maxlength="50" placeholder="Chicken"></label>
        @endif
        <label>Price (NGN)<input type="number" name="{{ $prefix }}[price]" value="{{ $item ? number_format($item->price / 100, 2, '.', '') : '' }}" min="1" max="999999.99" step="0.01" required></label>
        <label>Position<input type="number" name="{{ $prefix }}[position]" value="{{ $item?->position ?? 0 }}" min="0" max="65535" required></label>
    </div>
    <input type="hidden" name="{{ $prefix }}[available]" value="0">
    <label class="check"><input type="checkbox" name="{{ $prefix }}[available]" value="1" @checked(!$item || $item->available)> Available</label>
    @if($item)
        <label class="check"><input type="checkbox" name="{{ $prefix }}[remove]" value="1"> Remove when saved</label>
    @else
        <button class="text-link" type="button" data-remove-option>Remove row</button>
    @endif
</fieldset>

