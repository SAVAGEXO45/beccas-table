@props(['hours'])
<dl class="hours">
    @foreach($hours as $day => $period)
        <div><dt>{{ $day }}</dt><dd>{{ $period['closed'] ? 'Closed' : \Carbon\CarbonImmutable::parse($period['open'])->format('g:i A').' – '.\Carbon\CarbonImmutable::parse($period['close'])->format('g:i A') }}</dd></div>
    @endforeach
</dl>
