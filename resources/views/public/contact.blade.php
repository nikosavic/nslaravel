<x-app-layout :title="__('ui.sections.contact.title')">
    <section class="section !pb-0">
        <div class="wrap">
            <div class="contact-panel px-6 py-12 sm:px-12 sm:py-16">
                <div class="pointer-events-none absolute -right-10 -top-10 h-24 w-24 rounded-full bg-gold/90 sm:-right-16 sm:-top-16 sm:h-56 sm:w-56" aria-hidden="true"></div>

                <div class="relative grid gap-10 lg:grid-cols-12">
                    <div class="lg:col-span-5">
                        <h1 class="section-title text-on-deep">{{ __('ui.sections.contact.title') }}</h1>
                        <p class="mt-4 max-w-sm text-lg leading-relaxed text-on-deep/80">{{ __('ui.sections.contact.desc') }}</p>
                    </div>

                    <div class="lg:col-span-7">
                        @include('public._contact-form')
                    </div>
                </div>
            </div>
        </div>

        @include('layouts.footer')
    </section>
</x-app-layout>
