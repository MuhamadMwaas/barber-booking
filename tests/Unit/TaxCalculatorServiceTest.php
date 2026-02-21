<?php

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use App\Services\TaxCalculatorService;

class TaxCalculatorServiceTest extends TestCase
{
    private TaxCalculatorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TaxCalculatorService();
    }

    /* ==========================================================
     |  extractTax – اختبارات قاسية
     ========================================================== */

    public function test_extract_tax_with_standard_value(): void
    {
        $result = $this->service->extractTax('119.00', '19', 2);

        $this->assertSame('100.00', $result['net']);
        $this->assertSame('19.00', $result['tax']);
        $this->assertSame('119.00', $result['gross']);

        $this->assertSame(
            '119.00',
            bcadd($result['net'], $result['tax'], 2)
        );
    }

    public function test_extract_tax_with_ugly_decimals(): void
    {
        $result = $this->service->extractTax('119.999999', '19', 2);

        $this->assertSame(
            $result['gross'],
            bcadd($result['net'], $result['tax'], 2)
        );
    }

    public function test_extract_tax_with_micro_amount(): void
    {
        $result = $this->service->extractTax('0.01', '19', 2);

        $this->assertSame(
            $result['gross'],
            bcadd($result['net'], $result['tax'], 2)
        );
    }

    public function test_extract_tax_zero_rate(): void
    {
        $result = $this->service->extractTax('100.00', '0', 2);

        $this->assertSame('100.00', $result['net']);
        $this->assertSame('0.00', $result['tax']);
        $this->assertSame('100.00', $result['gross']);
    }

    public function test_extract_tax_100_percent(): void
    {
        $result = $this->service->extractTax('200.00', '100', 2);

        $this->assertSame('100.00', $result['net']);
        $this->assertSame('100.00', $result['tax']);
        $this->assertSame('200.00', $result['gross']);
    }

    public function test_extract_tax_precision_4(): void
    {
        $result = $this->service->extractTax('123.4567', '19', 4);

        $this->assertSame(
            $result['gross'],
            bcadd($result['net'], $result['tax'], 4)
        );
    }

    /* ==========================================================
     |  calculateBulk – اختبارات تجميع قاتلة
     ========================================================== */

    public function test_calculate_bulk_with_multiple_tax_rates(): void
    {
        $items = [
            ['price' => '19.99', 'tax_rate' => '19'],
            ['price' => '5.49', 'tax_rate' => '7'],
            ['price' => '99.95', 'tax_rate' => '19'],
            ['price' => '0.99', 'tax_rate' => '7'],
            ['price' => '249.99', 'tax_rate' => '19'],
            ['price' => '1.01', 'tax_rate' => '7'],
            ['price' => '333.33', 'tax_rate' => '19'],
            ['price' => '88.88', 'tax_rate' => '0'],
            ['price' => '12.34', 'tax_rate' => '5'],
            ['price' => '9.99', 'tax_rate' => '19'],
            ['price' => '7.77', 'tax_rate' => '7'],
            ['price' => '66.66', 'tax_rate' => '19'],
            ['price' => '3.33', 'tax_rate' => '0'],
            ['price' => '44.44', 'tax_rate' => '5'],
            ['price' => '2.22', 'tax_rate' => '7'],
            ['price' => '555.55', 'tax_rate' => '19'],
            ['price' => '8.88', 'tax_rate' => '0'],
            ['price' => '11.11', 'tax_rate' => '5'],
            ['price' => '6.66', 'tax_rate' => '7'],
            ['price' => '77.77', 'tax_rate' => '19'],
        ];

        $bulk = $this->service->calculateBulk($items, 2);

        $sumNet = '0';
        $sumTax = '0';
        $sumGross = '0';

        foreach ($items as $item) {
            $r = $this->service->extractTax($item['price'], $item['tax_rate'], 2);
            $sumNet = bcadd($sumNet, $r['net'], 2);
            $sumTax = bcadd($sumTax, $r['tax'], 2);
            $sumGross = bcadd($sumGross, $r['gross'], 2);
        }

        $this->assertSame($sumNet, $bulk['net']);
        $this->assertSame($sumTax, $bulk['tax']);
        $this->assertSame($sumGross, $bulk['gross']);
        $this->assertSame(
            $bulk['gross'],
            bcadd($bulk['net'], $bulk['tax'], 2)
        );
    }

    public function test_calculate_bulk_randomized_stress(): void
    {
        $items = [];
        $rates = ['0', '5', '7', '19'];

        for ($i = 0; $i < 100; $i++) {
            $items[] = [
                'price' => bcdiv((string) rand(1, 100000), '100', 4),
                'tax_rate' => $rates[array_rand($rates)],
            ];
        }

        $bulk = $this->service->calculateBulk($items, 2);

        $sumNet = '0';
        $sumTax = '0';
        $sumGross = '0';

        foreach ($items as $item) {
            $r = $this->service->extractTax($item['price'], $item['tax_rate'], 2);
            $sumNet = bcadd($sumNet, $r['net'], 2);
            $sumTax = bcadd($sumTax, $r['tax'], 2);
            $sumGross = bcadd($sumGross, $r['gross'], 2);
        }

        $this->assertSame($sumNet, $bulk['net']);
        $this->assertSame($sumTax, $bulk['tax']);
        $this->assertSame($sumGross, $bulk['gross']);
    }

    /* ==========================================================
     |  اختبارات استثناءات (حماية)
     ========================================================== */

    public function test_negative_tax_rate_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->extractTax('100', '-5', 2);
    }

    public function test_tax_rate_above_100_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->extractTax('100', '150', 2);
    }

    public function test_invalid_precision_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->extractTax('100', '19', 20);
    }
}
