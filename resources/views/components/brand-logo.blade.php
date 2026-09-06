@props([
    'iconOnly' => false,
    'size' => 'size-9 sm:size-10',
    'wordmarkClass' => 'text-xl',
    'inverse' => false,
])

{{-- Icon-only controls must supply their accessible name on the surrounding link/button. --}}
<span {{ $attributes->class(['inline-flex shrink-0 items-center gap-3 whitespace-nowrap']) }}>
    <img src="{{ asset('brand-mark.svg') }}" alt="" width="100" height="100" class="{{ $size }} shrink-0 object-contain">
    @unless ($iconOnly)
        <span @class([$wordmarkClass, 'font-extrabold tracking-tight', 'text-white' => $inverse, 'text-slate-900' => ! $inverse])>3A Track<span @class(['text-amber-400' => $inverse, 'text-orange-700' => ! $inverse])>Pro</span></span>
    @endunless
</span>
