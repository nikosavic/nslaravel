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