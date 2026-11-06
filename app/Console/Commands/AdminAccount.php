<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AdminAccount extends Command
{
    protected $signature = 'admin:account
        {email? : Email of the account to reset. Leave empty to list all accounts}
        {--make-admin : Also give this account the admin role}';

    protected $description = 'List accounts, or reset one account\'s password to a new random one';

    public function handle(): int
    {
        $email = $this->argument('email');

        if (! $email) {
            $users = User::orderBy('id')->get(['id', 'name', 'email', 'role']);

            if ($users->isEmpty()) {
                $this->warn('There are no accounts yet. Run: php artisan admin:account you@example.com');
                return self::SUCCESS;
            }

            $this->table(['ID', 'Name', 'Email', 'Role'], $users->map(fn ($u) => [$u->id, $u->name, $u->email, $u->role])->all());

            return self::SUCCESS;
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $user = new User(['name' => Str::before($email, '@'), 'email' => $email]);
            $this->line('No account with that email, so a new one will be created.');
            $this->option('make-admin') || $this->line('Tip: add --make-admin to make it an admin.');
        }

        $password = Str::password(20, symbols: false);
        $user->password = $password;

        if ($this->option('make-admin')) {
            $user->role = 'admin';
        }

        $user->save();

        $this->info("Account: {$user->email} (role: {$user->role})");
        $this->info("New password: {$password}");
        $this->line('Log in at /login, then change the password on the profile page.');

        return self::SUCCESS;
    }
}
