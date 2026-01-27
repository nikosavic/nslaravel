@props(['class' => 'h-9 w-auto'])

<img
    src="{{ asset('images/ns-logo.png') }}"
    alt="NS logo"
    {{ $attributes->merge(['class' => $class . ' logo-glow']) }}
/>