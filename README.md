# nikolasavic.dev

Personal site of Nikola Savic: a bilingual (EN/TR) Laravel portfolio with an admin area for projects, plus
**GameHub** (`/games`), a private game-library tracker with public profile and automatic syncing from Steam,
PlayStation and Xbox.

* Laravel 12, Blade, Tailwind 3, Alpine, Livewire 3 (GameHub only)
* Design tokens live in `resources/css/app.css`; fonts are self-hosted through `@fontsource-variable`.
* Content strings: `lang/en/ui.php` and `lang/tr/ui.php`.
* GameHub code: `app/Livewire`, `app/Services`, `resources/views/livewire`, `routes/gamehub.php`, `public/css/gamehub.css`.

## Local development

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
npm install && npm run dev      # or: npm run build
php artisan serve
php artisan test
```

Run the queue while testing syncs: `php artisan queue:listen` (or `php artisan gamehub:sync --now`).

Deployment: see [docs/DEPLOY.md](docs/DEPLOY.md).
