<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    /**
     * @param  string|null  $title  Page title; the site name is appended.
     * @param  string|null  $description  Meta/OG description; the site default when empty.
     * @param  string|null  $ogImage  Absolute URL of the link-preview image; the site logo when empty.
     */
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?string $ogImage = null,
    )
    {
    }

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.app');
    }
}
