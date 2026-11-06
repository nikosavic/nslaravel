# Deploying nikolasavic.dev (site + GameHub) with Forge

GameHub lives inside this app at `/games`. It uses the normal site login and only the site admin can open it.
Visitors only ever see the optional public profile (`/games/u/<name>`), which you switch on in GameHub → Profile.

## 1. One-time, on your computer

```bash
git checkout redesign-and-gamehub
composer require livewire/livewire:^3.6     # adds Livewire to composer.json AND composer.lock
npm install
php artisan migrate                          # GameHub tables + platform list
npm run build
php artisan test                             # everything should be green
```

Commit the two changed files (`composer.json`, `composer.lock`) and push. Open a pull request, wait for the green
CI check (`.github/workflows/ci.yml`), then merge into `main`.

> Until Livewire is in `composer.lock` the site still works: the `/games` routes and the GameHub menu button simply
> switch themselves off instead of breaking the deploy.

## 2. Forge: environment

Site → Environment, make sure these exist (copy values from your local `.env`):

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://nikolasavic.dev
QUEUE_CONNECTION=database
SESSION_DRIVER=database

STEAM_API_KEY=...
IGDB_CLIENT_ID=...
IGDB_CLIENT_SECRET=...
XBOX_CLIENT_ID=...        # only for the direct Xbox login
```

PlayStation needs no key here: you paste your NPSSO on GameHub → Accounts.

## 3. Forge: deploy script

Site → Deployments → replace the script with:

```bash
cd $FORGE_SITE_PATH
git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Frontend (Vite writes public/build, which is not in git)
npm ci
npm run build

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
    $FORGE_PHP artisan storage:link || true
    $FORGE_PHP artisan config:cache
    $FORGE_PHP artisan view:cache
    $FORGE_PHP artisan queue:restart
fi
```

(Do not add `artisan optimize` or `route:cache`: two routes in `routes/web.php` are closures, which cannot be route-cached.)

## 4. Forge: scheduler and queue worker (this is what makes syncing automatic)

* **Scheduler**: Site → Scheduler (or Server → Scheduler) → add `php8.3 /home/forge/nikolasavic.dev/artisan schedule:run`,
  every minute. Use the PHP version your site runs. That one entry runs, from `routes/console.php`:
  * `gamehub:sync` every 6 hours (queues one sync per linked account),
  * `gamehub:time-to-beat` daily at 04:00,
  * `gamehub:monthly-summary` on the 1st at 08:00 (uses the site's mail settings).
* **Queue worker**: Site → Queue → New worker: connection `database`, queue `default`, processes `1`. Syncs run there,
  not in a web request.

## 5. First run on production

1. Make sure your user is the admin (you already use Admin Projects, so it is).
2. Open `https://nikolasavic.dev/games`, sign in, go to **Accounts** and link Steam, PlayStation and Xbox again.
   Linked-account tokens are encrypted with the app key, and production has its own `APP_KEY`, so rows from your
   local database cannot be reused. Stats history starts again from the first production sync.
3. Check on the server: `php artisan schedule:list` shows the three GameHub tasks, and
   `php artisan gamehub:sync --now` prints one line per account.

## Troubleshooting

| Symptom | Fix |
|---|---|
| No "GameHub" button in the menu | Livewire is not in `composer.lock` yet (step 1), or the deploy did not run `composer install`. |
| Page unstyled / Vite manifest error | `npm ci && npm run build` is missing from the deploy script, or Node is not installed on the server. |
| `/games` says 403 | The signed-in user is not `role = admin`. |
| Syncs never start | Queue worker not created, or scheduler entry missing (step 4). |
| Project images missing | Run `php artisan storage:link` once. |
| Roll back | Forge → Deployments → redeploy an earlier commit. The new migrations only add tables and columns. |
