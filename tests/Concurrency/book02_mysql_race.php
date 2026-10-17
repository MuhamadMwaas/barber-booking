<?php

/**
 * BOOK-02 — the real race, on real MySQL, with real separate connections.
 *
 * The Pest suite runs on SQLite, which drops FOR UPDATE, and on ONE connection,
 * so it can only prove the structure (lock first, then check). This script
 * proves the behaviour: N separate PHP processes, each with its own MySQL
 * connection, all try to book the same provider at the same minute, released
 * by a shared start time.
 *
 * To make the race certain rather than lucky, every worker sleeps for
 * --delay-ms inside Appointment::creating — i.e. AFTER its conflict check has
 * said "free" and BEFORE its INSERT. That is the exact TOCTOU window.
 *
 *   Scenario "locked"   — the real code. Expected: exactly 1 booking, the
 *                         rest refused with SlotUnavailableException.
 *   Scenario "unlocked" — BookingLockService replaced by a no-op: the pre-fix
 *                         behaviour. Expected: several bookings on one slot.
 *                         This is the control — it shows the script CAN see a
 *                         double booking, so a clean "locked" run means something.
 *
 * Safety: it only ever touches the database named in RACE_DB, which it creates
 * and migrate:fresh-es itself. It refuses to run if the connection resolves to
 * anything else, or if the config is cached (env overrides would be ignored).
 *
 * Usage (MySQL must be running):
 *   php tests/Concurrency/book02_mysql_race.php [--workers=8] [--delay-ms=300]
 *
 * Exit code 0 = the lock held. Not part of the Pest suite (tests/Concurrency is
 * outside the testsuite directories) because it needs a live MySQL server.
 */

const RACE_DB = 'barberbooking_race_test';

// ── Environment: must be set before Laravel reads .env ────────────────────────
// Laravel's env repository is immutable, so values already present win over
// .env. Mail, queue, SMS and push are all neutralised: a worker must never
// reach a real customer.
$raceEnv = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'mysql',
    'DB_DATABASE' => RACE_DB,
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SMS_ENABLED' => 'false',
    'VONAGE_SMS_ENABLED' => 'false',
    'ONESIGNAL_APP_ID' => '',
    'ONESIGNAL_REST_API_KEY' => '',
    'FISKALY_ENABLED' => 'false',
    'BCRYPT_ROUNDS' => '4',
    'TELESCOPE_ENABLED' => 'false',
    'PULSE_ENABLED' => 'false',
    'NIGHTWATCH_ENABLED' => 'false',
];

foreach ($raceEnv as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

$root = dirname(__DIR__, 2);

require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\User;
use App\Services\BookingLockService;
use App\Services\BookingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\SalonFixture;

$options = getopt('', ['worker', 'mode:', 'customer:', 'provider:', 'service:', 'time:', 'start-at:', 'delay-ms:', 'workers:']);

if ($app->configurationIsCached()) {
    fwrite(STDERR, "Config is cached — the env overrides above would be ignored. Run `php artisan config:clear` first.\n");
    exit(2);
}

if (DB::connection()->getDriverName() !== 'mysql' || config('database.connections.mysql.database') !== RACE_DB) {
    fwrite(STDERR, 'Refusing to run: connection is not mysql/'.RACE_DB."\n");
    exit(2);
}

isset($options['worker']) ? runWorker($options) : runParent($options, __FILE__);

// ═════════════════════════════════════════════════════════════════════════════

function runWorker(array $o): never
{
    Carbon::setTestNow(Carbon::parse(SalonFixture::NOW));

    if ($o['mode'] === 'unlocked') {
        // The pre-fix world: the check still runs inside the transaction, but
        // nothing makes the competitors wait for each other.
        app()->instance(BookingLockService::class, new class extends BookingLockService {
            public function lockUsers(array $userIds): void {}
        });
    }

    // Widen the gap between "the check said free" and "the INSERT" so every
    // worker is inside it at the same time.
    $delayUs = (int) $o['delay-ms'] * 1000;
    Appointment::creating(fn () => usleep($delayUs));

    // Shared start line: all workers fire within the same few milliseconds.
    while (microtime(true) < (float) $o['start-at']) {
        usleep(500);
    }

    try {
        $appointment = app(BookingService::class)->createBooking(User::findOrFail((int) $o['customer']), [
            'date' => SalonFixture::DATE,
            'payment_method' => 'cash',
            'services' => [[
                'service_id' => (int) $o['service'],
                'provider_id' => (int) $o['provider'],
                'start_time' => $o['time'],
            ]],
        ]);
        $result = ['outcome' => 'BOOKED', 'detail' => $appointment->number];
    } catch (SlotUnavailableException $e) {
        $result = ['outcome' => 'SLOT_CONFLICT', 'detail' => $e->getMessage()];
    } catch (InvalidArgumentException $e) {
        $result = ['outcome' => 'REJECTED', 'detail' => $e->getMessage()];
    } catch (Throwable $e) {
        $result = ['outcome' => 'ERROR', 'detail' => get_class($e).': '.$e->getMessage()];
    }

    echo json_encode($result + ['customer' => (int) $o['customer']]).PHP_EOL;
    exit(0);
}

function runParent(array $o, string $script): never
{
    $workers = (int) ($o['workers'] ?? 8);
    $delayMs = (int) ($o['delay-ms'] ?? 300);

    line('Preparing '.RACE_DB.' (create + migrate:fresh) …');
    createRaceDatabase();
    Artisan::call('migrate:fresh', ['--force' => true]);

    $salon = new SalonFixture;
    $customers = [];
    for ($i = 1; $i <= $workers; $i++) {
        $customer = User::factory()->create([
            'first_name' => 'Racer',
            'last_name' => (string) $i,
            'phone' => sprintf('+4915100000%03d', $i),
            'is_active' => true,
        ]);
        $customer->assignRole('customer');
        $customers[] = $customer->id;
    }

    $results = [
        'locked' => race($script, 'locked', '10:00', $salon, $customers, $delayMs),
        'unlocked' => race($script, 'unlocked', '14:00', $salon, $customers, $delayMs),
    ];

    $lockedBooked = $results['locked']['rows'];
    $unlockedBooked = $results['unlocked']['rows'];

    line('');
    line('══════════════ RESULT ══════════════');
    line("locked   (the fix):     {$lockedBooked} appointment(s) on one slot — expected 1");
    line("unlocked (the old way): {$unlockedBooked} appointment(s) on one slot — expected > 1 (control)");

    if ($unlockedBooked <= 1) {
        line('⚠ The control did not reproduce the double booking; raise --delay-ms. The locked result proves nothing until it does.');
        exit(1);
    }

    if ($lockedBooked !== 1) {
        line('✗ BOOK-02 IS NOT FIXED: the lock let more than one booking through.');
        exit(1);
    }

    line('✓ BOOK-02 holds on MySQL: concurrent requests queued on the provider lock and only one won.');
    exit(0);
}

/** @return array{rows:int} */
function race(string $script, string $mode, string $time, SalonFixture $salon, array $customers, int $delayMs): array
{
    line('');
    line("── Scenario: {$mode} — ".count($customers)." workers → {$salon->available->full_name} @ ".SalonFixture::DATE." {$time}");

    $startAt = microtime(true) + 3.0;   // time for every worker to boot
    $procs = [];

    foreach ($customers as $customerId) {
        $cmd = [PHP_BINARY, $script, '--worker',
            "--mode={$mode}", "--customer={$customerId}",
            "--provider={$salon->available->id}", "--service={$salon->service->id}",
            "--time={$time}", "--start-at={$startAt}", "--delay-ms={$delayMs}"];

        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $procs[] = [$proc, $pipes];
    }

    $tally = [];
    foreach ($procs as [$proc, $pipes]) {
        $out = trim(stream_get_contents($pipes[1]));
        $err = trim(stream_get_contents($pipes[2]));
        proc_close($proc);

        $lastLine = $out === '' ? '' : (string) last(explode("\n", $out));
        $result = json_decode($lastLine, true) ?? ['outcome' => 'ERROR', 'detail' => $err ?: $out, 'customer' => '?'];
        $tally[$result['outcome']] = ($tally[$result['outcome']] ?? 0) + 1;
        line(sprintf('   customer %-4s %-14s %s', $result['customer'], $result['outcome'], mb_strimwidth((string) $result['detail'], 0, 90, '…')));
    }

    // Ground truth from the database, not from what the workers reported.
    $rows = Appointment::where('provider_id', $salon->available->id)
        ->whereDate('appointment_date', SalonFixture::DATE)
        ->where('start_time', SalonFixture::DATE." {$time}:00")
        ->blocksProviderTime()
        ->count();

    line('   tally: '.json_encode($tally).'  |  rows in DB on that slot: '.$rows);

    return ['rows' => $rows];
}

function createRaceDatabase(): void
{
    $c = config('database.connections.mysql');
    $pdo = new PDO("mysql:host={$c['host']};port={$c['port']}", $c['username'], $c['password']);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.RACE_DB.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    DB::purge('mysql');
}

function line(string $text): void
{
    fwrite(STDOUT, $text.PHP_EOL);
}
