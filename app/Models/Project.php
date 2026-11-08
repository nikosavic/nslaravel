<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'summary',
        'body',
        'repo_url',
        'live_url',
        'image_path',
        'featured',
        'sort_order',
        'status',

        // translations
        'title_en', 'title_tr',
        'summary_en', 'summary_tr',
        'body_en', 'body_tr',
    ];

    protected $casts = [
        'featured' => 'boolean',
    ];

    /**
     * Always return the localized title/summary/body based on app locale.
     * Fallback order:
     * - current locale column
     * - English column
     * - base column (title/summary/body)
     */
    public function getTitleAttribute($value): ?string
    {
        $loc = app()->getLocale();
        return $this->pickLocalized('title', $loc) ?? $value;
    }

    public function getSummaryAttribute($value): ?string
    {
        $loc = app()->getLocale();
        return $this->pickLocalized('summary', $loc) ?? $value;
    }

    public function getBodyAttribute($value): ?string
    {
        $loc = app()->getLocale();
        return $this->pickLocalized('body', $loc) ?? $value;
    }

    /** Folder (inside public/) where screenshots committed with the site live: images/projects/{slug}/ */
    private function imageDir(): string
    {
        return public_path('images/projects/'.$this->slug);
    }

    /** @return list<string> file names in the screenshot folder, sorted, cover excluded */
    private function imageFiles(bool $cover = false): array
    {
        $files = glob($this->imageDir().'/*.{png,jpg,jpeg,webp}', GLOB_BRACE) ?: [];
        $files = array_map('basename', $files);
        sort($files, SORT_NATURAL);

        return array_values(array_filter($files, fn ($f) => $cover
            ? str_starts_with($f, 'cover.')
            : ! str_starts_with($f, 'cover.') && ! str_starts_with($f, 'og.')));
    }

    /**
     * Cover image URL: the file uploaded in Admin > Projects, else images/projects/{slug}/cover.*, else null.
     * A stored path that starts with "images/" is served from public/ instead of the storage disk.
     */
    public function imageUrl(): ?string
    {
        if ($this->image_path) {
            return str_starts_with($this->image_path, 'images/')
                ? asset($this->image_path)
                : asset('storage/'.$this->image_path);
        }

        $cover = $this->slug ? ($this->imageFiles(true)[0] ?? null) : null;

        return $cover ? asset('images/projects/'.$this->slug.'/'.$cover) : null;
    }

    /** Screenshots shown under the summary: every other image in images/projects/{slug}/ in name order. */
    public function gallery(): array
    {
        if (! $this->slug) {
            return [];
        }

        return array_map(fn ($f) => asset('images/projects/'.$this->slug.'/'.$f), $this->imageFiles());
    }

    /** Link-preview image: images/projects/{slug}/og.* if present, else the cover, else null. */
    public function shareImageUrl(): ?string
    {
        if ($this->slug) {
            $og = glob($this->imageDir().'/og.{png,jpg,jpeg,webp}', GLOB_BRACE)[0] ?? null;

            if ($og) {
                return asset('images/projects/'.$this->slug.'/'.basename($og));
            }
        }

        return $this->imageUrl();
    }

    private function pickLocalized(string $base, string $locale): ?string
    {
        $locale = strtolower($locale);

        $col = "{$base}_{$locale}";
        if (in_array($col, $this->getFillable(), true) && !empty($this->{$col})) {
            return $this->{$col};
        }

        $fallback = "{$base}_en";
        if (in_array($fallback, $this->getFillable(), true) && !empty($this->{$fallback})) {
            return $this->{$fallback};
        }

        return null;
    }
}