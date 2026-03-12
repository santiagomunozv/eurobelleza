@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block border-l-4 border-white bg-[#1c4789] py-2 pl-3 pr-4 text-base font-medium text-white focus:outline-none transition duration-150 ease-in-out'
            : 'block border-l-4 border-transparent py-2 pl-3 pr-4 text-base font-medium text-white/80 hover:border-white/60 hover:bg-[#1c4789] hover:text-white focus:outline-none focus:border-white/60 focus:bg-[#1c4789] focus:text-white transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
