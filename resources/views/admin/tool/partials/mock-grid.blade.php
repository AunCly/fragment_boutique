{{-- Static pixel grid for the tool mockup. $cells: rows of essences [code, hex]; $mode: flat | wood | plan --}}
@php
    $mode ??= 'flat';
    $class ??= '';
    $cols = count($cells[0]);
@endphp
<div class="grid {{ $mode === 'flat' ? '' : 'gap-px' }} {{ $mode === 'plan' ? 'bg-foreground/15 p-px' : '' }} {{ $mode === 'wood' ? 'bg-black/25 p-px' : '' }} {{ $class }}" style="grid-template-columns: repeat({{ $cols }}, minmax(0, 1fr));">
    @foreach ($cells as $r => $row)
        @foreach ($row as $c => [$code, $hex])
            @if ($mode === 'wood')
                <div class="aspect-square" style="background-color: {{ $hex }}; background-image: repeating-linear-gradient({{ (($r * 7 + $c * 3) % 4) * 90 }}deg, rgba(0,0,0,.09) 0 1px, transparent 1px 3px, rgba(255,255,255,.07) 3px 4px, transparent 4px 6px);"></div>
            @elseif ($mode === 'plan')
                <div class="aspect-square bg-white flex items-center justify-center font-mono text-[7px] leading-none text-foreground/70">{{ $code }}</div>
            @else
                <div class="aspect-square" style="background-color: {{ $hex }};"></div>
            @endif
        @endforeach
    @endforeach
</div>
