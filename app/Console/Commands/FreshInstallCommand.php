<?php

namespace App\Console\Commands;

use Database\Seeders\EssentialSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Wipes the whole database and seeds only the essentials (roles, permissions,
 * settings, salon hours, invoice templates, site pages and ONE admin) —
 * see {@see EssentialSeeder}. Irreversible: every booking, invoice and user
 * is dropped, so take a backup first.
 */
class FreshInstallCommand extends Command
{
    protected $signature = 'app:fresh-install
                            {--email= : Admin e-mail (default: admin@<APP_URL host>)}
                            {--password= : Admin password (default: random 20 characters)}
                            {--force : Skip the confirmation prompt}';

    protected $description = 'Drop all tables, re-run migrations and seed only the essential production data';

    public function handle(): int
    {
        $email = $this->option('email');
        if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Invalid e-mail: {$email}");

            return self::FAILURE;
        }

        $password = $this->option('password');
        if ($password !== null && strlen($password) < 12) {
            $this->error('The admin password must be at least 12 characters.');

            return self::FAILURE;
        }

        $database = config('database.connections.' . config('database.default') . '.database');
        $this->warn("  This DELETES ALL DATA in database [{$database}] on [" . app()->environment() . '].');

        if (! $this->option('force') && ! $this->confirm('Type yes to continue', false)) {
            $this->info('Aborted — nothing was changed.');

            return self::FAILURE;
        }

        config([
            'seeding.admin_email' => $email,
            'seeding.admin_password' => $password ?? Str::password(20),
        ]);

        $this->call('migrate:fresh', ['--force' => true]);
        $this->call('db:seed', ['--class' => EssentialSeeder::class, '--force' => true]);
        $this->call('optimize:clear');

        $this->info('  Fresh install complete.');

        return self::SUCCESS;
    }
}
