<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\TaxCalculatorService;

class AdvancedTaxCalculatorTest extends TestCase
{
    private TaxCalculatorService $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new TaxCalculatorService();
    }

    /**
     * اختبار تحمل النظام لأعلى الأحمال
     */
    public function testSystemLoadTolerance(): void
    {
        $this->markTestSkipped('اختبار الحمل الثقيل - للتشغيل عند الحاجة');

        // اختبار متتابع لقياس استقرار النظام
        $iterations = [10, 100, 1000, 10000];

        foreach ($iterations as $count) {
            echo "\n\n=== اختبار {$count} عنصر ===";

            $items = $this->generateStressTestItems($count);

            $start = microtime(true);
            $result = $this->calculator->calculateBulk($items, 4);
            $time = microtime(true) - $start;

            $this->assertConditionNetPlusTaxEqualsGross($result, 4);

            echo "\nالوقت: " . round($time, 4) . " ثانية";
            echo "\nNet: " . $result['net'];
            echo "\nTax: " . $result['tax'];
            echo "\nGross: " . $result['gross'];

            // التأكد من عدم وجود تسريب في الذاكرة
            $memory = memory_get_peak_usage(true) / 1024 / 1024;
            echo "\nالذاكرة القصوى: " . round($memory, 2) . " MB";

            $this->assertLessThan(100, $memory, "استخدام ذاكرة مفرط");
        }
    }

    /**
     * اختبار الحسابات المالية الحساسة
     */
    public function testFinancialCriticalCalculations(): void
    {
        // حسابات مالية حساسة تتطلب دقة عالية
        $criticalCases = [
            // عمولة بنكية مع ضريبة
            [
                'items' => [
                    ['price' => '1000000.00', 'tax_rate' => '15'], // قرض
                    ['price' => '50000.00', 'tax_rate' => '20'],   // عمولة
                    ['price' => '25000.00', 'tax_rate' => '0'],    // رسوم معفاة
                ],
                'precision' => 2
            ],
            // عقارات مع ضرائب مختلفة
            [
                'items' => [
                    ['price' => '5000000.00', 'tax_rate' => '5'],  // عقار سكني
                    ['price' => '2000000.00', 'tax_rate' => '10'], // عقار تجاري
                    ['price' => '1000000.00', 'tax_rate' => '15'], // أرض
                ],
                'precision' => 2
            ],
            // استثمارات بمعدلات دقيقة
            [
                'items' => [
                    ['price' => '999999.9999', 'tax_rate' => '13.337'],
                    ['price' => '888888.8888', 'tax_rate' => '7.7777'],
                    ['price' => '777777.7777', 'tax_rate' => '19.9999'],
                ],
                'precision' => 4
            ]
        ];

        foreach ($criticalCases as $case) {
            $result = $this->calculator->calculateBulk($case['items'], $case['precision']);

            // التحقق المتعدد
            $this->assertConditionNetPlusTaxEqualsGross($result, $case['precision']);

            // التحقق من أن الضريبة محسوبة بشكل معقول
            $taxPercentage = bcdiv($result['tax'], $result['gross'], $case['precision'] + 2);
            $taxPercentage = bcmul($taxPercentage, '100', $case['precision']);

            // يجب أن تكون النسبة بين 0 و 100
            $this->assertGreaterThanOrEqual('0', $taxPercentage);
            $this->assertLessThanOrEqual('100', $taxPercentage);
        }
    }

    /**
     * اختبار إعادة الحساب العكسي
     */
    public function testReverseCalculationConsistency(): void
    {
        // اختبار أن extractTax ثم addTax يعيدان نفس القيمة الأصلية
        $testValues = [
            ['gross' => '1000.00', 'rate' => '20', 'precision' => 2],
            ['gross' => '1234.5678', 'rate' => '13.5', 'precision' => 4],
            ['gross' => '9999.999999', 'rate' => '7.7777', 'precision' => 6],
        ];

        foreach ($testValues as $test) {
            // الخطوة 1: استخراج الضريبة
            $extracted = $this->calculator->extractTax(
                $test['gross'],
                $test['rate'],
                $test['precision']
            );

            // الخطوة 2: إضافة الضريبة لنفس الـ net
            $added = $this->calculator->addTax(
                $extracted['net'],
                $test['rate'],
                $test['precision']
            );

            // يجب أن تكون القيم متطابقة
            $this->assertEqualsWithPrecision(
                $test['gross'],
                $added['gross'],
                $test['precision'],
                "فشل الحساب العكسي للقيم: {$test['gross']}, {$test['rate']}"
            );

            $this->assertEqualsWithPrecision(
                $extracted['net'],
                $added['net'],
                $test['precision'],
                "فشل تطابق Net في الحساب العكسي"
            );

            $this->assertEqualsWithPrecision(
                $extracted['tax'],
                $added['tax'],
                $test['precision'],
                "فشل تطابق Tax في الحساب العكسي"
            );
        }
    }

    /**
     * اختبار الحالات الحدية للدقة
     */
    public function testPrecisionEdgeCases(): void
    {
        // دقة صفرية (أرقام صحيحة)
        //
        // 100 ÷ 1.15 = 86.9565…  →  التقريب الصحيح هو 87، لا 86.
        // كان هذا الاختبار يؤكّد '86' بينما تعليقه نفسه يقول «→ 87»: كُتب
        // ليطابق الكود المعطوب الذي كان يقتطع بـ bcdiv بدل أن يقرّب، فصار
        // يحرس العطل بدل أن يكشفه (MON-01).
        $result = $this->calculator->extractTax('100', '15', 0);
        $this->assertEquals('87', $result['net']); // 100 / 1.15 = 86.96 → 87
        $this->assertEquals('13', $result['tax']); // 100 - 87 = 13
        $this->assertEquals('100', $result['gross']);

        // دقة عالية جداً (10)
        $result = $this->calculator->extractTax('1.0000000001', '19.9999999999', 10);
        $this->assertConditionNetPlusTaxEqualsGross($result, 10);

        // الانتقال بين دقة مختلفة لنفس القيمة
        $gross = '123.456789';
        $rate = '15.75';

        $results = [];
        for ($precision = 0; $precision <= 8; $precision++) {
            $results[$precision] = $this->calculator->extractTax($gross, $rate, $precision);
            $this->assertConditionNetPlusTaxEqualsGross($results[$precision], $precision);
        }

        // التحقق من أن الزيادة في الدقة تقلل الخطأ.
        //
        // التسامح **نسبي بالدقة**، لا ثابتاً. كان الاختبار يستخدم '0.1'
        // مطلقةً لكل المقارنات، وهي قيمة مستحيلة عند مقارنة الدقة 0 بالدقة 1:
        //
        //     123.456789 ÷ 1.1575 = 106.6538…
        //     بدقة 0 → 107      (تقريب لأقرب واحد صحيح)
        //     بدقة 1 → 106.7
        //     الفرق  = 0.3      ← أكبر من 0.1 وهو مع ذلك صحيح تماماً
        //
        // القيمة المقرَّبة بدقة i تبعد عن الحقيقية بما لا يزيد عن ‎0.5×10⁻ⁱ‎،
        // وبدقة i+1 بما لا يزيد عن ‎0.05×10⁻ⁱ‎، فالحد الأقصى لفرقهما
        // ‎0.55×10⁻ⁱ‎. (هذا التأكيد لم يكن يُنفَّذ أصلاً: الاختبار كان يتوقف
        // قبله عند تأكيد '86' الخاطئ في أول الدالة.)
        for ($i = 0; $i < 7; $i++) {
            $current = $results[$i];
            $next = $results[$i + 1];

            // net الحالي يجب أن يكون قريباً من net التالي عند رفعه للدقة الأعلى
            $currentNetHigherPrecision = $this->bcRound($current['net'], $i + 1);

            // القيمة المطلقة بـ bcmath، لا بـ abs(): abs() على نص bcmath
            // يُرجع float، والقيم الصغيرة جداً تُطبع بصيغة أسّية ('1.0E-9')
            // فترفضها bcmath بـ ValueError.
            $difference = bcsub($currentNetHigherPrecision, $next['net'], $i + 2);
            if (bccomp($difference, '0', $i + 2) === -1) {
                $difference = bcmul($difference, '-1', $i + 2);
            }

            $tolerance = bcdiv('0.55', bcpow('10', (string) $i, 0), $i + 2);

            $this->assertLessThanOrEqual(
                0,
                bccomp($difference, $tolerance, $i + 2),
                "الفرق الكبير بين الدقة {$i} و " . ($i + 1)
                    . " (الفرق {$difference}، المسموح {$tolerance})"
            );
        }
    }

    /**
     * اختبار التزامن (Concurrency)
     */
    public function testConcurrentCalculations(): void
    {
        // محاكاة حسابات متزامنة
        $itemsSets = [
            $this->generateComplexServices(100),
            $this->generateComplexServices(200),
            $this->generateComplexServices(150),
            $this->generateComplexServices(300),
        ];

        $results = [];
        $promises = [];

        // في بيئة حقيقية، يمكن استخدام Promise أو Multi-threading
        // هنا نستخدم حلقات بسيطة للمحاكاة
        foreach ($itemsSets as $index => $items) {
            $results[$index] = $this->calculator->calculateBulk($items, 4);
            $this->assertConditionNetPlusTaxEqualsGross($results[$index], 4);
        }

        // التحقق من أن جميع النتائج صحيحة
        foreach ($results as $result) {
            $this->assertIsArray($result);
            $this->assertArrayHasKey('net', $result);
            $this->assertArrayHasKey('tax', $result);
            $this->assertArrayHasKey('gross', $result);
        }
    }

    // ========== الدوال المساعدة ==========

    private function generateStressTestItems(int $count): array
    {
        $items = [];
        $basePrice = '100.00';

        for ($i = 0; $i < $count; $i++) {
            // تغيير طفيف في السعر ومعدل الضريبة
            $priceVariation = bcdiv((string) $i, '1000', 6);
            $price = bcadd($basePrice, $priceVariation, 6);

            $taxRate = bcadd(
                '15.00',
                bcdiv((string) ($i % 37), '10', 2),
                2
            );

            if (bccomp($taxRate, '100', 2) > 0) {
                $taxRate = bcdiv($taxRate, '2', 2);
            }

            $items[] = [
                'price' => $price,
                'tax_rate' => $taxRate
            ];
        }

        return $items;
    }

    private function assertConditionNetPlusTaxEqualsGross(array $result, int $precision): void
    {
        $sum = bcadd($result['net'], $result['tax'], $precision);
        $this->assertEquals(
            $result['gross'],
            $sum,
            "شرط net + tax = gross فشل"
        );
    }

    private function assertEqualsWithPrecision(string $expected, string $actual, int $precision, string $message = ''): void
    {
        $expectedRounded = $this->bcRound($expected, $precision);
        $actualRounded = $this->bcRound($actual, $precision);

        $this->assertEquals($expectedRounded, $actualRounded, $message);
    }

    private function bcRound(string $number, int $precision): string
    {
        if ($precision < 0) {
            throw new \InvalidArgumentException('Precision must be >= 0');
        }

        $sign = '';
        if (str_starts_with($number, '-')) {
            $sign = '-';
            $number = substr($number, 1);
        }

        $shift = bcpow('10', (string) $precision, 0);
        $shifted = bcmul($number, $shift, $precision + 6);
        $rounded = bcadd($shifted, '0.5', 0);
        $floored = bcdiv($rounded, '1', 0);
        $result = bcdiv($floored, $shift, $precision);

        return $sign . $result;
    }

      private function generateComplexServices(int $count): array
    {
        $services = [];
        $primeNumbers = [2, 3, 5, 7, 11, 13, 17, 19, 23, 29];

        for ($i = 0; $i < $count; $i++) {
            $prime = $primeNumbers[$i % count($primeNumbers)];
            $price = bcmul((string) ($i + 1), '123.456789', 10);
            $taxRate = bcadd(
                bcdiv((string) ($i * 7), '3', 6),
                bcdiv((string) $prime, '2', 6),
                6
            );

            // تأكد من أن معدل الضريبة بين 0 و 100.
            //
            // كان هذا `if` واحداً يقسم على 2 مرة واحدة، وهو لا يكفي: عند
            // count=300 يصل المعدل المولَّد إلى ~700، فقسمةٌ واحدة تُنزله إلى
            // ~350 — أي أنه يبقى فوق 100 فترفضه الحاسبة بـ
            // InvalidArgumentException، فيفشل الاختبار بسبب مولّده لا بسبب
            // الكود المُختبَر. `while` تُنزله فعلاً إلى المجال المطلوب.
            while (bccomp($taxRate, '100', 6) > 0) {
                $taxRate = bcdiv($taxRate, '2', 6);
            }

            $services[] = [
                'price' => $price,
                'tax_rate' => $taxRate
            ];
        }

        return $services;
    }
}
