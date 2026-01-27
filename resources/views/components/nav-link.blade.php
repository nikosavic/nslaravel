@props(['active'])

@php
    $base = 'relative inline-flex items-center px-2 pt-2 text-sm font-medium transition duration-200 ease-out group';
    $state = ($active ?? false)
        ? 'text-gray-900 dark:text-white'
        : 'text-gray-600 hover:text-gray-900 dark:text-white/70 dark:hover:text-white';
@endphp

<a {{ $attributes->merge(['class' => $base . ' ' . $state]) }}>
    <span class="relative">
        {{ $slot }}

        {{-- underline (hover anim) --}}
        <span class="pointer-events-none absolute left-0 -bottom-2 h-[2px] w-full origin-left scale-x-0 rounded-full
                     bg-gradient-to-r from-fuchsia-500 via-indigo-500 to-cyan-400
                     transition-transform duration-300 ease-out
                     group-hover:scale-x-100"></span>

        {{-- glow pulse on hover (a blurred copy behind the underline) --}}
        <span class="pointer-events-none absolute left-0 -bottom-2 h-[2px] w-full origin-left scale-x-0 rounded-full blur-[6px]
                     bg-gradient-to-r from-fuchsia-500 via-indigo-500 to-cyan-400 opacity-60
                     transition-transform duration-300 ease-out
                     group-hover:scale-x-100 group-hover:animate-pulse"></span>

        {{-- active underline --}}
        @if($active ?? false)
            <span class="pointer-events-none absolute left-0 -bottom-2 h-[2px] w-full rounded-full
                         bg-gradient-to-r from-fuchsia-500 via-indigo-500 to-cyan-400"></span>
            <span class="pointer-events-none absolute left-0 -bottom-2 h-[2px] w-full rounded-full blur-[6px]
                         bg-gradient-to-r from-fuchsia-500 via-indigo-500 to-cyan-400 opacity-60"></span>
        @endif
    </span>
</a>