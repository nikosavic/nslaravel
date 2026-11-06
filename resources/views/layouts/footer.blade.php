<footer class="relative overflow-hidden pb-10 pt-24 sm:pt-32">
    <div class="wrap relative z-10 flex flex-col gap-10 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex items-center gap-4">
            <span class="logo-chip h-14 w-14"><x-application-logo class="h-10 w-10 object-contain" /></span>
            <div class="font-display text-xl font-bold tracking-tight">{{ __('ui.footer.tagline') }}</div>
        </div>

        <div class="flex items-center gap-7 text-[15px] font-semibold">
            <a href="https://www.linkedin.com/in/nikola-savic-74a1aa26b/" target="_blank" rel="noopener noreferrer"
               class="underline decoration-2 underline-offset-4 decoration-gold hover:decoration-ink">{{ __('ui.footer.linkedin') }}</a>
            <a href="https://www.instagram.com/iamnikosavic" target="_blank" rel="noopener noreferrer"
               class="underline decoration-2 underline-offset-4 decoration-gold hover:decoration-ink">{{ __('ui.footer.instagram') }}</a>
        </div>
    </div>

    <div class="wrap relative z-10 mt-10 text-sm text-ink/60">
        © {{ date('Y') }} {{ __('ui.footer.copyright') }}
    </div>

    <div class="wordmark mt-6 px-4 text-center" aria-hidden="true">{{ __('ui.about.name') }}</div>
</footer>
