<?php

namespace Tests\Unit;

use App\Services\DocumentNumberGenerator;
use RuntimeException;
use Tests\TestCase;

/**
 * MON-03 — the generator must refuse to hand out a number it cannot reserve.
 *
 * Deliberately extends Tests\TestCase WITHOUT RefreshDatabase.
 *
 * The app has to be booted for the DB facade to resolve, but the database must
 * NOT be wrapped in a transaction: RefreshDatabase (used by the whole feature
 * suite) opens one around every test, which makes DB::transactionLevel() 1 and
 * the guard correctly stay silent. This is the only place the refusal itself
 * can be observed.
 *
 * No migrations are needed either — the guard is the first statement in next()
 * and runs before any query is issued.
 */
class DocumentNumberGeneratorGuardTest extends TestCase
{
    public function test_it_refuses_to_issue_a_number_outside_a_transaction(): void
    {
        // Taking a number in one transaction and writing it in another reserves
        // nothing: the counter lock is released at commit. That is exactly what
        // the old implementation did — DB::transaction() inside generate() —
        // so the generator now rejects the shape instead of returning an
        // unprotected number.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/must be called inside a\s+transaction/');

        DocumentNumberGenerator::next('invoice', 'INV');
    }
}
