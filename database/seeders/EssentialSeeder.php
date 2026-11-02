<?php

namespace Database\Seeders;

use App\Enum\RegistrationMethod;
use App\Models\Branch;
use App\Models\PrinterSetting;
use App\Models\SalonSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Production bootstrap: only what the system needs to run — no demo
 * providers, customers, services or appointments.
 *
 * Normally run through `php artisan app:fresh-install`, which also wipes the
 * database first. Running it alone (`db:seed --class=EssentialSeeder`) expects
 * an empty, freshly migrated database.
 *
 * The admin credentials come from config `seeding.admin_*` (set at runtime by
 * the command); without them a random password is generated and printed once.
 */
class EssentialSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            LanguageSeeder::class,
            PermissionsSeeder::class,
            RoleSeeder::class,
            AppSettingSeeder::class,
            PaymentMethodSeeder::class,
            ReasonLeaveSeeder::class,
            InvoiceTemplateSeeder::class,
        ]);

        $branch = $this->seedBranch();

        // Both read Branch::all(), so they pick up the branch above.
        $this->call([
            SalonSettingSeeder::class,
            SalonScheduleSeeder::class,
        ]);
        $this->localiseSalonSettings($branch);

        $this->seedDefaultPrinter();

        // Public site + legal pages (shipped copy, editable from Filament).
        $this->call([
            StaticPagesSeeder::class,
            AboutUsPageSeeder::class,
            SliderSeeder::class,
            PrivacyPolicyPageSeeder::class,
            TermsConditionsPageSeeder::class,
            ImpressumPageSeeder::class,
            LandingPageSeeder::class,
        ]);

        $this->seedAdmin($branch);
    }

    private function seedBranch(): Branch
    {
        return Branch::create([
            'name' => config('app.name') . ' - Berlin',
            // '' not null: SalonSettingSeeder copies them into NOT NULL setting values.
            'phone' => '',
            'email' => '',
            'is_active' => true,
        ]);
    }

    /**
     * SalonSettingSeeder ships the old UAE demo values; a German salon needs
     * EUR and no AED loyalty key. Company fields stay empty on purpose — the
     * admin must fill them (§14 UStG) from the settings screen.
     */
    private function localiseSalonSettings(Branch $branch): void
    {
        SalonSetting::where('branch_id', $branch->id)
            ->where('key', 'currency')
            ->update(['value' => json_encode('EUR')]);

        SalonSetting::where('branch_id', $branch->id)
            ->where('key', 'points_per_aed')
            ->delete();
    }

    private function seedDefaultPrinter(): void
    {
        PrinterSetting::create([
            'name' => 'Main POS Printer',
            'printer_name' => 'Thermal 80mm',
            'description' => 'Default receipt printer',
            'connection_type' => 'usb',
            'paper_size' => '80mm',
            'default_copies' => 1,
            'print_method' => 'browser',
            'is_active' => true,
            'is_default' => true,
        ]);
    }

    private function seedAdmin(Branch $branch): void
    {
        $email = config('seeding.admin_email') ?: 'admin@' . (parse_url(config('app.url'), PHP_URL_HOST) ?: 'example.com');
        $password = config('seeding.admin_password') ?: Str::password(20);

        $admin = User::create([
            'first_name' => 'System',
            'last_name' => 'Admin',
            'email' => $email,
            'password' => Hash::make($password),
            'user_type' => Role::where('name', 'admin')->value('id'),
            'locale' => 'de',
            'is_active' => true,
            'registration_method' => RegistrationMethod::EMAIL,
            'email_verified_at' => now(),
            'branch_id' => $branch->id,
        ]);
        $admin->assignRole('admin');

        $this->command?->line('');
        $this->command?->warn('  Admin account — copy the password now, it is not stored anywhere:');
        $this->command?->line("    Email:    <fg=green>{$email}</>");
        $this->command?->line("    Password: <fg=green>{$password}</>");
        $this->command?->line('');
    }
}
