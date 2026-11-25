@props(['name', 'label', 'value' => null, 'type' => 'text', 'rows' => null])
<label>
    {{ $label }}
    @if($rows)
        <textarea name="{{ $name }}" rows="{{ $rows }}" {{ $attributes }}>{{ old($name, $value) }}</textarea>
    @else
        <input type="{{ $type }}" name="{{ $name }}" value="{{ old($name, $value) }}" {{ $attributes }}>
    @endif
</label>
