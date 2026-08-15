<?php

namespace Tests\Support;

use App\Enum\AppointmentStatus;
use App\Enum\PaymentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Language;
use App\Models\ProviderScheduledWork;
use App\Models\ProviderTimeOff;
use App\Models\ReasonLeave;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * One salon, populated so that every branch of the availability logic has a
 * provider that lands in it.
 *
 * The point is that a single call to `/availability/service` must return the
 * `available` provider and NOTHING else — each other provider here is a
 * different reason the code is supposed to exclude someone. If a filter breaks,
 * exactly one named provider leaks into the response and the failure message
 * says which rule broke.
 *
 * Time is frozen by the caller (see `freezeTime()`); every slot assertion in the
 * suite depends on "now" being fixed, otherwise a test run that straddles a
 * minute boundary produces a different slot list.
 */
class SalonFixture
{
    /** Frozen "now": Monday 2026-09-07, 08:00 — before any shift starts. */
    public const NOW = '2026-09-07 08:00:00';

    /** Target booking day: Wednesday 2026-09-09 (now + 2 days, inside the window). */
    public const DATE = '2026-09-09';

    public Branch $branch;

    public Branch $otherBranch;

    public ServiceCategory $category;

    /** 60 minutes, 100.00 gross — divides the 09:00-17:00 shift into 8 clean slots. */
    public Service $service;

    /** A second service the main provider also offers, for multi-service bookings. */
    public Service $secondService;

    /** A service nobody in this fixture provides. */
    public Service $unofferedService;

    public Service $inactiveService;

    public User $customer;

    /**
     * Owns the pre-seeded appointments used to occupy slots. Kept separate from
     * `$customer` so the fixture's own bookings never count against the customer
     * under test — otherwise `max_daily_bookings` and "list my bookings" both see
     * traffic the test never created.
     */
    public User $filler;

    /** Works 09:00-17:00 on the target day, no leave, no bookings. The only one that should show up. */
    public User $available;

    /** Works that day, but has a full-day leave covering it. */
    public User $onLeave;

    /** Has no schedule row for that weekday at all. */
    public User $notWorking;

    /** Works that day but every slot is already taken by confirmed appointments. */
    public User $fullyBooked;

    /** Offers the service, works that day — but the user account is disabled. */
    public User $inactiveUser;

    /** Works that day, but the provider_service pivot is switched off. */
    public User $inactivePivot;

    /** Fully available, but attached to a different branch. */
    public User $otherBranchProvider;

    /** Fully available, but does not offer the service under test. */
    public User $doesNotOffer;

    /** Works that day with an hourly leave 11:00-14:00 carved out. */
    public User $hourlyLeave;

    public static function freezeTime(): void
    {
        Carbon::setTestNow(Carbon::parse(self::NOW));
    }

    public function __construct()
    {
        self::freezeTime();

        foreach (['customer', 'provider', 'admin', 'manager'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->seedSettings();

        // Production always has languages seeded with one default. Without a
        // default row Service::translation() dereferences null — see the
        // regression test in Step1ServiceAndLoginTest.
        Language::firstOrCreate(
            ['code' => 'en'],
            ['name' => 'English', 'native_name' => 'English', 'is_active' => true, 'is_default' => true],
        );

        $this->branch = Branch::create(['name' => 'Main Branch']);
        $this->otherBranch = Branch::create(['name' => 'Second Branch']);

        $this->category = ServiceCategory::create([
            'name' => 'Hair',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->service = $this->makeService('Hair Cut', 60, 100.00);
        $this->secondService = $this->makeService('Beard Trim', 60, 50.00);
        $this->unofferedService = $this->makeService('Nobody Offers This', 60, 30.00);
        $this->inactiveService = $this->makeService('Retired Service', 60, 30.00, false);

        $this->customer = $this->makeCustomer();

        $this->filler = User::factory()->create([
            'first_name' => 'Filler',
            'last_name' => 'Customer',
        ]);
        $this->filler->assignRole('customer');

        $dayOfWeek = Carbon::parse(self::DATE)->dayOfWeek;

        $this->available = $this->makeProvider('Available', $this->branch, $dayOfWeek);
        $this->onLeave = $this->makeProvider('OnLeave', $this->branch, $dayOfWeek);
        $this->notWorking = $this->makeProvider('NotWorking', $this->branch, null);
        $this->fullyBooked = $this->makeProvider('FullyBooked', $this->branch, $dayOfWeek);
        $this->inactiveUser = $this->makeProvider('InactiveUser', $this->branch, $dayOfWeek);
        $this->inactivePivot = $this->makeProvider('InactivePivot', $this->branch, $dayOfWeek);
        $this->otherBranchProvider = $this->makeProvider('OtherBranch', $this->otherBranch, $dayOfWeek);
        $this->doesNotOffer = $this->makeProvider('DoesNotOffer', $this->branch, $dayOfWeek);
        $this->hourlyLeave = $this->makeProvider('HourlyLeave', $this->branch, $dayOfWeek);

        // Everyone offers the service under test except `doesNotOffer`.
        foreach ([
            $this->available, $this->onLeave, $this->notWorking, $this->fullyBooked,
            $this->inactiveUser, $this->otherBranchProvider, $this->hourlyLeave,
        ] as $provider) {
            $this->offerService($provider, $this->service);
        }

        $this->offerService($this->available, $this->secondService);
        $this->offerService($this->doesNotOffer, $this->secondService);
        $this->offerService($this->inactivePivot, $this->service, isActive: false);
        $this->offerService($this->available, $this->inactiveService);

        $this->inactiveUser->update(['is_active' => false]);

        $this->giveFullDayLeave($this->onLeave, self::DATE, self::DATE);
        $this->giveHourlyLeave($this->hourlyLeave, self::DATE, '11:00:00', '14:00:00');

        // Fill the whole 09:00-17:00 shift so no 60-minute slot survives.
        for ($hour = 9; $hour < 17; $hour++) {
            $this->bookSlot(
                $this->fullyBooked,
                self::DATE,
                sprintf('%02d:00', $hour),
                sprintf('%02d:00', $hour + 1),
            );
        }
    }

    /**
     * Settings the booking rules read. Written explicitly rather than relying on
     * `get_setting()` defaults so a change to a default cannot silently rewrite
     * what these tests assert.
     */
    private function seedSettings(array $overrides = []): void
    {
        $settings = $overrides + [
            'tax_rate' => '19',
            'book_buffer' => '0',
            'max_booking_days' => '10',
            'max_services_per_booking' => '10',
            'max_daily_bookings' => '10',
        ];

        foreach ($settings as $key => $value) {
            DB::table('salon_settings')->updateOrInsert(
                ['key' => $key],
                [
                    'value' => json_encode($value),
                    'type' => 'string',
                    'description' => 'test fixture',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    public function setSetting(string $key, string $value): void
    {
        $this->seedSettings([$key => $value]);
    }

    private function makeService(string $name, int $minutes, float $price, bool $active = true): Service
    {
        return Service::create([
            'category_id' => $this->category->id,
            'name' => $name,
            'description' => $name,
            'price' => $price,
            'duration_minutes' => $minutes,
            'is_active' => $active,
            'sort_order' => 1,
            'color_code' => '#000000',
        ]);
    }

    /**
     * The customer the manual QA account mirrors — same credentials as the real
     * seeded account so the login assertions match what a human would type.
     */
    private function makeCustomer(): User
    {
        $customer = User::factory()->create([
            'first_name' => 'Lina',
            'last_name' => 'Hassan',
            'email' => 'lina.hassan@gmail.com',
            'password' => 'password',
            'registration_method' => 'email',
            'email_verified_at' => now(),
            'email_verified_via_otp_at' => now(),
            'is_active' => true,
        ]);

        $customer->assignRole('customer');

        return $customer;
    }

    private function makeProvider(string $label, Branch $branch, ?int $dayOfWeek): User
    {
        $provider = User::factory()->create([
            'first_name' => $label,
            'last_name' => 'Provider',
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $provider->assignRole('provider');

        if ($dayOfWeek !== null) {
            ProviderScheduledWork::create([
                'user_id' => $provider->id,
                'day_of_week' => $dayOfWeek,
                'start_time' => '09:00',
                'end_time' => '17:00',
                'is_work_day' => true,
                'is_active' => true,
            ]);
        }

        return $provider;
    }

    public function offerService(User $provider, Service $service, bool $isActive = true): void
    {
        DB::table('provider_service')->insert([
            'provider_id' => $provider->id,
            'service_id' => $service->id,
            'is_active' => $isActive,
        ]);
    }

    /**
     * Inserted through the query builder, NOT the model, on purpose.
     *
     * The model casts start_date/end_date to `date`, and Eloquent then persists
     * them with the connection's datetime format. On MySQL the DATE column keeps
     * "2026-09-09"; on the SQLite test connection the same write lands as
     * "2026-09-09 00:00:00". BookingValidationService compares those columns as
     * plain strings (`where('start_date', '<=', $date)`), so the padded SQLite
     * value silently fails a comparison that succeeds in production — tests would
     * report a leave bug that does not exist on the real database.
     *
     * Writing the raw `Y-m-d` string keeps both engines byte-identical, so what
     * the suite proves about leave handling actually holds in production.
     */
    public function giveFullDayLeave(User $provider, string $start, ?string $end): int
    {
        return DB::table('provider_time_offs')->insertGetId([
            'user_id' => $provider->id,
            'type' => ProviderTimeOff::TYPE_FULL_DAY,
            'start_date' => $start,
            'end_date' => $end,
            'reason_id' => ReasonLeave::firstOrCreate(['name' => 'Vacation'])->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Raw insert for the same reason as {@see giveFullDayLeave()}. */
    public function giveHourlyLeave(User $provider, string $date, string $from, string $to): int
    {
        return DB::table('provider_time_offs')->insertGetId([
            'user_id' => $provider->id,
            'type' => ProviderTimeOff::TYPE_HOURLY,
            'start_date' => $date,
            'end_date' => $date,
            'start_time' => $from,
            'end_time' => $to,
            'reason_id' => ReasonLeave::firstOrCreate(['name' => 'Appointment'])->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * A confirmed booking that blocks the slot. `created_status = 1` is what the
     * booking validator looks at when detecting conflicts.
     */
    public function bookSlot(
        User $provider,
        string $date,
        string $from,
        string $to,
        int $createdStatus = 1,
        AppointmentStatus $status = AppointmentStatus::PENDING,
    ): Appointment {
        return Appointment::create([
            'number' => 'APT-' . str()->random(10),
            'provider_id' => $provider->id,
            'customer_id' => $this->filler->id,
            'appointment_date' => $date,
            'start_time' => "{$date} {$from}:00",
            'end_time' => "{$date} {$to}:00",
            'duration_minutes' => 60,
            'subtotal' => 84.03,
            'tax_amount' => 15.97,
            'total_amount' => 100.00,
            'status' => $status,
            'payment_status' => PaymentStatus::PENDING,
            'created_status' => $createdStatus,
        ]);
    }

    /** Bearer token for the fixture customer. */
    public function customerToken(): string
    {
        return $this->customer->createToken('test')->plainTextToken;
    }
}
