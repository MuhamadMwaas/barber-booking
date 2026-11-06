<?php

/**
 * The salon settings screens, driven with rows shaped like the seeded database:
 * values go through the JSON cast, booleans are the strings "true"/"false", and
 * the company group exists.
 */

use App\Filament\Resources\SalonSettings\Pages\EditSalonSetting;
use App\Filament\Resources\SalonSettings\Pages\ListSalonSettings;
use App\Filament\Resources\SalonSettings\Pages\ViewSalonSetting;
use App\Filament\Resources\SalonSettings\SalonSettingCatalog;
use App\Filament\Resources\SalonSettings\SalonSettingResource;
use App\Models\SalonSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    app()->setLocale('en');

    Role::findOrCreate('SuperAdmin', 'web');
    $admin = User::create([
        'first_name' => 'Settings',
        'last_name' => 'Admin',
        'email' => 'settings-admin-'.uniqid().'@example.com',
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);
    $admin->assignRole('SuperAdmin');
    $this->actingAs($admin->fresh());
});

function salonSetting(string $key, mixed $value, string $type, string $group = 'company'): SalonSetting
{
    return SalonSetting::create([
        'key' => $key,
        'value' => $value,
        'type' => $type,
        'setting_group' => $group,
        'description' => "stored description of {$key}",
    ]);
}

function storedValue(SalonSetting $setting): mixed
{
    return json_decode(DB::table('salon_settings')->where('id', $setting->id)->value('value'), true);
}

it('shows the group of a company setting on the edit page', function () {
    $setting = salonSetting('company_email', '', 'string');

    Livewire::test(EditSalonSetting::class, ['record' => $setting->id])
        ->assertOk()
        ->assertSee('Company');
});

it('saves a company setting', function () {
    $setting = salonSetting('company_email', '', 'string');

    Livewire::test(EditSalonSetting::class, ['record' => $setting->id])
        ->fillForm(['value' => 'hello@salon.de'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(storedValue($setting))->toBe('hello@salon.de');
});

it('stores an empty string when an optional setting is cleared', function () {
    $setting = salonSetting('company_phone', '030 123', 'string');

    Livewire::test(EditSalonSetting::class, ['record' => $setting->id])
        ->fillForm(['value' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(storedValue($setting))->toBe('');
});

it('refuses to clear the company name', function () {
    $setting = salonSetting('company_name', 'Look up', 'string');

    Livewire::test(EditSalonSetting::class, ['record' => $setting->id])
        ->fillForm(['value' => null])
        ->call('save')
        ->assertHasFormErrors(['value' => 'required']);
});

it('keeps the browser from filling in a setting', function () {
    $setting = salonSetting('company_email', '', 'string');

    Livewire::test(EditSalonSetting::class, ['record' => $setting->id])
        ->assertSeeHtml('autocomplete="off"');
});

it('rejects a malformed email', function () {
    $setting = salonSetting('company_email', '', 'string');

    Livewire::test(EditSalonSetting::class, ['record' => $setting->id])
        ->fillForm(['value' => 'not-an-email'])
        ->call('save')
        ->assertHasFormErrors(['value' => 'email']);
});

it('keeps max_services_per_booking a number the booking check can compare', function () {
    $setting = salonSetting('max_services_per_booking', 10, 'integer', 'general');

    Livewire::test(EditSalonSetting::class, ['record' => $setting->id])
        ->fillForm(['value' => 4])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(storedValue($setting))->toBe(4)
        ->and(get_setting('max_services_per_booking') < 5)->toBeTrue();
});

it('enforces numeric limits', function (string $key, mixed $value, string $type) {
    $setting = salonSetting($key, 10, $type, 'booking');

    Livewire::test(EditSalonSetting::class, ['record' => $setting->id])
        ->fillForm(['value' => $value])
        ->call('save')
        ->assertHasFormErrors(['value']);
})->with([
    'tax above 100' => ['tax_rate', 101, 'decimal'],
    'negative tax' => ['tax_rate', -1, 'decimal'],
    'zero services' => ['max_services_per_booking', 0, 'integer'],
    'fractional days' => ['max_booking_days', 1.5, 'integer'],
]);

it('saves a boolean as the string the app stores', function () {
    $setting = salonSetting('loyalty_points_enabled', 'true', 'boolean', 'loyalty');

    Livewire::test(EditSalonSetting::class, ['record' => $setting->id])
        ->assertSchemaStateSet(['value' => true])
        ->fillForm(['value' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(storedValue($setting))->toBe('false');
});

it('reads a double-encoded list and saves a real one', function () {
    $setting = salonSetting('payment_methods', '["cash", "card"]', 'json', 'payment');

    Livewire::test(EditSalonSetting::class, ['record' => $setting->id])
        ->assertSchemaStateSet(['value' => ['cash', 'card']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(storedValue($setting))->toBe(['cash', 'card']);
});

it('writes only the value, whatever the client sends', function () {
    $setting = salonSetting('company_phone', '030 123', 'string');

    Livewire::test(EditSalonSetting::class, ['record' => $setting->id])
        ->set('data.key', 'tax_rate')
        ->set('data.type', 'integer')
        ->set('data.setting_group', 'payment')
        ->call('save')
        ->assertHasNoFormErrors();

    $row = DB::table('salon_settings')->where('id', $setting->id)->first();

    expect($row->key)->toBe('company_phone')
        ->and($row->type)->toBe('string')
        ->and($row->setting_group)->toBe('company');
});

it('renders the view page without doubling the percent sign', function () {
    $setting = salonSetting('tax_rate', '19', 'decimal', 'payment');

    Livewire::test(ViewSalonSetting::class, ['record' => $setting->id])
        ->assertOk()
        ->assertSee('19 %')
        ->assertDontSee('%%');
});

it('renders every value type on the list and view pages', function () {
    $settings = collect([
        salonSetting('company_email', '', 'string'),
        salonSetting('book_buffer', '0', 'integer', 'booking'),
        salonSetting('max_booking_days', 60, 'integer', 'booking'),
        salonSetting('tax_rate', '19', 'decimal', 'payment'),
        salonSetting('auto_confirm_appointments', 'false', 'boolean', 'booking'),
        salonSetting('payment_methods', ['cash', 'card'], 'json', 'payment'),
        salonSetting('made_up_key', 'x', 'unknown_type', 'made_up_group'),
    ]);

    Livewire::test(ListSalonSettings::class)
        ->assertOk()
        ->assertCanSeeTableRecords($settings)
        ->assertSee('Made Up Group');

    foreach ($settings as $setting) {
        Livewire::test(ViewSalonSetting::class, ['record' => $setting->id])->assertOk();
        Livewire::test(EditSalonSetting::class, ['record' => $setting->id])->assertOk();
    }
});

it('filters the list by group tab', function () {
    $company = salonSetting('company_name', 'Look up', 'string');
    $booking = salonSetting('book_buffer', '0', 'integer', 'booking');

    Livewire::test(ListSalonSettings::class)
        ->set('activeTab', 'company')
        ->assertCanSeeTableRecords([$company])
        ->assertCanNotSeeTableRecords([$booking]);
});

it('finds a setting by its translated label', function () {
    $tax = salonSetting('tax_rate', '19', 'decimal', 'payment');
    $name = salonSetting('company_name', 'Look up', 'string');

    Livewire::test(ListSalonSettings::class)
        ->searchTable('Tax rate')
        ->assertCanSeeTableRecords([$tax])
        ->assertCanNotSeeTableRecords([$name]);
});

it('warns on a setting no code reads', function () {
    $unused = salonSetting('cancellation_hours', '24', 'integer', 'booking');
    $used = salonSetting('book_buffer', '0', 'integer', 'booking');

    Livewire::test(EditSalonSetting::class, ['record' => $unused->id])
        ->assertSee(__('resources.salon_setting.not_in_use_title'));

    Livewire::test(EditSalonSetting::class, ['record' => $used->id])
        ->assertDontSee(__('resources.salon_setting.not_in_use_title'));
});

it('cannot create or delete settings', function () {
    $setting = salonSetting('tax_rate', '19', 'decimal', 'payment');

    expect(SalonSettingResource::canCreate())->toBeFalse()
        ->and(SalonSettingResource::canDelete($setting))->toBeFalse()
        ->and(SalonSettingResource::canDeleteAny())->toBeFalse()
        ->and(SalonSettingResource::getPages())->not->toHaveKey('create');
});

it('labels every catalog group and setting in every language', function (string $locale) {
    app()->setLocale($locale);
    $settings = __('resources.salon_setting.settings');

    foreach (SalonSettingCatalog::groups() as $group) {
        expect(__("resources.salon_setting.group_{$group}"))->not->toStartWith('resources.');
    }

    foreach (['tax_rate', 'company_email', 'points_per_aed'] as $key) {
        expect(SalonSettingCatalog::label($key))->toBe($settings[$key]['label']);
    }

    expect($settings)->toHaveCount(20);
})->with(['ar', 'de', 'en']);

it('repairs the seeded rows', function () {
    $broken = salonSetting('max_services_per_booking', 'false', 'boolean', 'general');
    $kept = salonSetting('max_services_per_booking', 7, 'boolean', 'general');
    $methods = salonSetting('payment_methods', '["cash", "card"]', 'json', 'payment');
    $daily = salonSetting('max_daily_bookings', '10', 'integer', 'booking');

    (require database_path('migrations/2026_11_06_000001_repair_salon_settings_types.php'))->up();

    expect(storedValue($broken))->toBe(10)
        ->and(storedValue($kept))->toBe(7)
        ->and($broken->fresh()->type)->toBe('integer')
        ->and(storedValue($methods))->toBe(['cash', 'card'])
        ->and($daily->fresh()->description)->toBe('Maximum bookings per customer per day');
});
