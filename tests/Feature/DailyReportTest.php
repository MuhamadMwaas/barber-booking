<?php

namespace Tests\Feature;

use App\Enum\AppointmentStatus;
use App\Enum\BookingSource;
use App\Enum\InvoiceStatus;
use App\Enum\PaymentStatus;
use App\Models\Appointment;
use App\Models\AppointmentService as AppointmentServiceModel;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ProviderAttendance;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Services\DailyReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Covers the Daily Report (Z-Report & Employee Summary).
 *
 * The behaviours worth locking down are the ones that are easy to regress and
 * expensive to notice: which DATE a payment is filed under, that the employee
 * table foots to the sales summary, and that the report stays behind its
 * permission.
 */
class DailyReportTest extends TestCase
{
    use RefreshDatabase;

    private DailyReportService $reports;

    private User $providerA;

    private User $providerB;

    private Service $service;

    private string $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reports = app(DailyReportService::class);
        $this->today = Carbon::today()->format('Y-m-d');

        Role::findOrCreate('provider', 'web');

        $this->providerA = $this->makeProvider('Ahmed', 'Hassan');
        $this->providerB = $this->makeProvider('Mona', 'Khalil');

        $category = ServiceCategory::create([
            'name' => 'Hair',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $this->service = Service::create([
            'category_id' => $category->id,
            'name' => 'Haircut',
            'description' => 'Test service',
            'price' => 50.00,
            'duration_minutes' => 30,
            'is_active' => true,
            'sort_order' => 1,
            'color_code' => '#f59e0b',
        ]);
    }

    // ── Date basis ───────────────────────────────────────────────────────

    /**
     * The headline rule: a report is about money COLLECTED that day, so a
     * booking scheduled yesterday but paid today belongs to today.
     */
    public function test_revenue_is_filed_under_the_collection_date_not_the_appointment_date(): void
    {
        $yesterday = Carbon::yesterday();

        $this->makePaidBooking(
            provider: $this->providerA,
            appointmentDate: $yesterday->format('Y-m-d'),
            amount: 50.00,
            method: PaymentStatus::PAID_ONSTIE_CASH,
            collectedAt: Carbon::today()->setTime(11, 0),
        );

        $todayReport = $this->reports->build($this->today, $this->today);
        $yesterdayReport = $this->reports->build($yesterday->format('Y-m-d'), $yesterday->format('Y-m-d'));

        $this->assertSame(50.00, $todayReport['sales']['total_amount']);
        $this->assertSame(0.0, $yesterdayReport['sales']['total_amount']);
    }

    /**
     * The fallback chain: bookings flagged paid at creation (API cash) never get
     * a payment row, so they must still be picked up via the appointment date
     * rather than vanishing from the report entirely.
     */
    public function test_a_paid_booking_without_a_payment_row_is_still_counted(): void
    {
        $this->makePaidBooking(
            provider: $this->providerA,
            appointmentDate: $this->today,
            amount: 40.00,
            method: PaymentStatus::PAID_ONSTIE_CASH,
            collectedAt: null, // no payment row, no finalized_at
        );

        $report = $this->reports->build($this->today, $this->today);

        $this->assertSame(40.00, $report['sales']['total_amount']);
        $this->assertSame(1, $report['sales']['buckets']['cash']['count']);
    }

    // ── Reconciliation ───────────────────────────────────────────────────

    public function test_cash_and_card_are_split_and_sum_to_the_total(): void
    {
        $this->makePaidBooking($this->providerA, $this->today, 50.00, PaymentStatus::PAID_ONSTIE_CASH);
        $this->makePaidBooking($this->providerB, $this->today, 30.00, PaymentStatus::PAID_ONSTIE_CARD);
        $this->makePaidBooking($this->providerB, $this->today, 20.00, PaymentStatus::PAID_ONSTIE_CARD);

        $sales = $this->reports->build($this->today, $this->today)['sales'];

        $this->assertSame(50.00, $sales['buckets']['cash']['amount']);
        $this->assertSame(50.00, $sales['buckets']['card']['amount']);
        $this->assertSame(100.00, $sales['total_amount']);
        $this->assertSame(3, $sales['total_count']);
    }

    /**
     * The employee table is what an owner pays commission from, so it must foot
     * to the sales summary exactly — never off by a rounding cent.
     */
    public function test_employee_totals_reconcile_with_the_sales_summary(): void
    {
        $this->makePaidBooking($this->providerA, $this->today, 50.00, PaymentStatus::PAID_ONSTIE_CASH);
        $this->makePaidBooking($this->providerB, $this->today, 33.33, PaymentStatus::PAID_ONSTIE_CARD);

        $report = $this->reports->build($this->today, $this->today);

        $this->assertSame(
            $report['sales']['total_amount'],
            $report['employees']['totals']['total'],
        );
        $this->assertSame(
            $report['sales']['buckets']['cash']['amount'],
            $report['employees']['totals']['cash'],
        );
    }

    /** A discount lives on the invoice; the report must show it and net it off. */
    public function test_a_discount_is_reported_and_deducted_from_sales(): void
    {
        $this->makePaidBooking(
            provider: $this->providerA,
            appointmentDate: $this->today,
            amount: 50.00,
            method: PaymentStatus::PAID_ONSTIE_CASH,
            discount: 10.00,
        );

        $report = $this->reports->build($this->today, $this->today);

        $this->assertSame(40.00, $report['sales']['total_amount']);
        $this->assertSame(10.00, $report['discounts']['total']);
        $this->assertSame(1, $report['discounts']['count']);
        $this->assertSame(50.00, $report['discounts']['before']);
    }

    // ── Enum handling ────────────────────────────────────────────────────

    /**
     * `booking_source` is an enum cast — comparing it as a raw string silently
     * reports every booking as in-person, which is exactly the bug this guards.
     */
    public function test_booking_source_split_reads_the_enum_correctly(): void
    {
        $this->makePaidBooking($this->providerA, $this->today, 50.00, PaymentStatus::PAID_ONSTIE_CASH, source: BookingSource::ONLINE);
        $this->makePaidBooking($this->providerB, $this->today, 50.00, PaymentStatus::PAID_ONSTIE_CASH, source: BookingSource::IN_PERSON);

        $operations = $this->reports->build($this->today, $this->today)['operations'];

        $this->assertSame(1, $operations['source_online']);
        $this->assertSame(1, $operations['source_in_person']);
    }

    // ── Non-money context ────────────────────────────────────────────────

    public function test_cancelled_and_unpaid_bookings_are_reported_by_appointment_date(): void
    {
        $this->makeAppointment($this->providerA, $this->today, 30.00, AppointmentStatus::ADMIN_CANCELLED, PaymentStatus::PENDING);
        $this->makeAppointment($this->providerB, $this->today, 50.00, AppointmentStatus::PENDING, PaymentStatus::PENDING);

        $operations = $this->reports->build($this->today, $this->today)['operations'];

        $this->assertSame(1, $operations['cancelled']);
        $this->assertSame(1, $operations['outstanding_count']);
        $this->assertSame(50.00, $operations['outstanding_amount']);
    }

    /** Working time comes from real attendance, never from the planned shift. */
    public function test_working_time_uses_real_attendance_and_is_null_without_it(): void
    {
        $this->makePaidBooking($this->providerA, $this->today, 50.00, PaymentStatus::PAID_ONSTIE_CASH);
        $this->makePaidBooking($this->providerB, $this->today, 50.00, PaymentStatus::PAID_ONSTIE_CASH);

        ProviderAttendance::create([
            'user_id' => $this->providerA->id,
            'work_date' => $this->today,
            'check_in_at' => Carbon::today()->setTime(9, 0),
            'check_out_at' => Carbon::today()->setTime(17, 30),
            'source' => 'dashboard',
        ]);

        $rows = collect($this->reports->build($this->today, $this->today)['employees']['rows'])
            ->keyBy('provider_id');

        $this->assertSame('09:00', $rows[$this->providerA->id]['attendance']['first']);
        $this->assertSame('17:30', $rows[$this->providerA->id]['attendance']['last']);
        $this->assertSame(510, $rows[$this->providerA->id]['attendance']['minutes']);
        $this->assertNull($rows[$this->providerB->id]['attendance']);
    }

    /** Idle staff must still appear, so an owner can see who took nothing. */
    public function test_providers_with_no_takings_still_appear(): void
    {
        $this->makePaidBooking($this->providerA, $this->today, 50.00, PaymentStatus::PAID_ONSTIE_CASH);

        $rows = collect($this->reports->build($this->today, $this->today)['employees']['rows'])
            ->keyBy('provider_id');

        $this->assertSame(0.0, $rows[$this->providerB->id]['total']);
        $this->assertSame(0, $rows[$this->providerB->id]['appointments']);
    }

    // ── Filtering & ranges ───────────────────────────────────────────────

    public function test_a_provider_filter_narrows_the_report_to_that_provider(): void
    {
        $this->makePaidBooking($this->providerA, $this->today, 50.00, PaymentStatus::PAID_ONSTIE_CASH);
        $this->makePaidBooking($this->providerB, $this->today, 70.00, PaymentStatus::PAID_ONSTIE_CASH);

        $report = $this->reports->build($this->today, $this->today, [$this->providerA->id]);

        $this->assertSame(50.00, $report['sales']['total_amount']);
        $this->assertTrue($report['meta']['is_filtered']);
        $this->assertCount(1, $report['employees']['rows']);
    }

    public function test_a_range_produces_one_daily_row_per_day_including_empty_ones(): void
    {
        $from = Carbon::today()->subDays(2);

        $this->makePaidBooking(
            provider: $this->providerA,
            appointmentDate: $from->format('Y-m-d'),
            amount: 50.00,
            method: PaymentStatus::PAID_ONSTIE_CASH,
            collectedAt: $from->copy()->setTime(12, 0),
        );

        $report = $this->reports->build($from->format('Y-m-d'), $this->today);

        $this->assertFalse($report['meta']['is_single_day']);
        $this->assertCount(3, $report['daily']);
        $this->assertSame(50.00, $report['daily'][0]['total']);
        $this->assertSame(0.0, $report['daily'][2]['total']);
    }

    /** A reversed range is a slip, not an empty report. */
    public function test_a_reversed_range_is_swapped_rather_than_returning_nothing(): void
    {
        $this->makePaidBooking($this->providerA, $this->today, 50.00, PaymentStatus::PAID_ONSTIE_CASH);

        $yesterday = Carbon::yesterday()->format('Y-m-d');
        $report = $this->reports->build($this->today, $yesterday);

        $this->assertSame(50.00, $report['sales']['total_amount']);
    }

    // ── Access control ───────────────────────────────────────────────────

    public function test_the_printable_report_requires_the_view_reports_permission(): void
    {
        Permission::findOrCreate('StaffDashboard:access', 'web');
        Permission::findOrCreate('StaffDashboard:view_reports', 'web');

        $withoutPermission = $this->makeProvider('No', 'Access');
        $withoutPermission->givePermissionTo('StaffDashboard:access');

        $this->actingAs($withoutPermission)
            ->get(route('staff.dashboard.report.print', ['from' => $this->today]))
            ->assertForbidden();

        $withPermission = $this->makeProvider('Has', 'Access');
        $withPermission->givePermissionTo(['StaffDashboard:access', 'StaffDashboard:view_reports']);

        $this->actingAs($withPermission)
            ->get(route('staff.dashboard.report.print', ['from' => $this->today]))
            ->assertOk();
    }

    public function test_the_printable_report_renders_the_figures(): void
    {
        Permission::findOrCreate('StaffDashboard:access', 'web');
        Permission::findOrCreate('StaffDashboard:view_reports', 'web');

        $this->makePaidBooking($this->providerA, $this->today, 50.00, PaymentStatus::PAID_ONSTIE_CASH);

        $admin = $this->makeProvider('Report', 'Viewer');
        $admin->givePermissionTo(['StaffDashboard:access', 'StaffDashboard:view_reports']);

        $this->actingAs($admin)
            ->get(route('staff.dashboard.report.print', ['from' => $this->today]))
            ->assertOk()
            ->assertSee('Ahmed Hassan')
            ->assertSee('50.00');
    }

    // ── Fixtures ─────────────────────────────────────────────────────────

    private function makeProvider(string $first, string $last): User
    {
        $user = User::factory()->create([
            'first_name' => $first,
            'last_name' => $last,
            'is_active' => true,
        ]);

        $user->assignRole('provider');

        return $user;
    }

    private function makeAppointment(
        User $provider,
        string $date,
        float $amount,
        AppointmentStatus $status,
        PaymentStatus $paymentStatus,
        BookingSource $source = BookingSource::IN_PERSON,
    ): Appointment {
        $net = round($amount / 1.19, 2);

        $appointment = Appointment::create([
            'provider_id' => $provider->id,
            'customer_name' => 'Guest ' . uniqid(),
            'customer_phone' => '+49' . random_int(1000000, 9999999),
            'appointment_date' => $date,
            'start_time' => Carbon::parse($date)->setTime(10, 0),
            'end_time' => Carbon::parse($date)->setTime(10, 30),
            'duration_minutes' => 30,
            'subtotal' => $net,
            'tax_amount' => round($amount - $net, 2),
            'total_amount' => $amount,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'created_status' => 1,
            'booking_source' => $source,
        ]);

        AppointmentServiceModel::create([
            'appointment_id' => $appointment->id,
            'service_id' => $this->service->id,
            'service_name' => $this->service->name,
            'duration_minutes' => 30,
            'price' => $amount,
            'sequence_order' => 1,
        ]);

        return $appointment;
    }

    /**
     * A fully collected booking. `collectedAt` drives which link of the
     * resolution chain is exercised: a Carbon writes a real payment row plus a
     * finalisation stamp, null writes neither (the API-cash shape).
     */
    private function makePaidBooking(
        User $provider,
        string $appointmentDate,
        float $amount,
        PaymentStatus $method,
        ?Carbon $collectedAt = null,
        float $discount = 0.0,
        BookingSource $source = BookingSource::IN_PERSON,
    ): Invoice {
        $appointment = $this->makeAppointment(
            $provider,
            $appointmentDate,
            $amount,
            AppointmentStatus::COMPLETED,
            $method,
            $source,
        );

        $payable = round($amount - $discount, 2);
        $net = round($payable / 1.19, 2);

        $invoice = Invoice::create([
            'appointment_id' => $appointment->id,
            'invoice_number' => 'INV-' . uniqid(),
            'subtotal' => $net,
            'tax_amount' => round($payable - $net, 2),
            'tax_rate' => 19.00,
            'total_amount' => $payable,
            'discount_amount' => $discount,
            'status' => InvoiceStatus::PAID,
            'invoice_data' => $collectedAt
                ? ['finalized_at' => $collectedAt->toISOString(), 'payment_type' => (string) $method->value]
                : [],
        ]);

        if ($collectedAt) {
            $payment = Payment::create([
                'payment_number' => 'PAY-' . uniqid(),
                'amount' => $payable,
                'subtotal' => $net,
                'tax_amount' => round($payable - $net, 2),
                'status' => $method,
                'type' => Payment::TYPE_FULL,
                'paymentable_id' => $invoice->id,
                'paymentable_type' => Invoice::class,
            ]);

            // `created_at` is not fillable and Eloquent stamps it with now(), so
            // it has to be forced afterwards — otherwise every fixture would
            // collect "today" and the collection-date tests would prove nothing.
            $payment->timestamps = false;
            $payment->forceFill(['created_at' => $collectedAt, 'updated_at' => $collectedAt])->save();
        }

        return $invoice;
    }
}
