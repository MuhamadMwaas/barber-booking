<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\TaxCalculatorService;
use InvalidArgumentException;

class TaxCalculatorService2Test extends TestCase
{
    private TaxCalculatorService $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new TaxCalculatorService();
    }

    /**
     * اختبارات قاسية جداً لـ extractTax مع حالات متطرفة
     */
    public function testExtractTaxWithExtremeValues(): void
    {
        // 1. حالات ضريبة صفرية مع أرقام معقدة
        $cases = [
            ['gross' => '999999999999.9999', 'rate' => '0', 'precision' => 2],
            ['gross' => '0.00000001', 'rate' => '0', 'precision' => 2],
            ['gross' => '1234567.89', 'rate' => '0.00', 'precision' => 2],
            ['gross' => '1000000000000', 'rate' => '0', 'precision' => 0],
        ];

        foreach ($cases as $case) {
            $result = $this->calculator->extractTax($case['gross'], $case['rate'], $case['precision']);

            $this->assertEquals($result['gross'], $result['net']);
            $this->assertEquals($this->formatZero($case['precision']), $result['tax']);
            $this->assertConditionNetPlusTaxEqualsGross($result, $case['precision']);
        }

        // 2. حالات ضريبة 100%
        $hundredPercentCases = [
            ['gross' => '100', 'precision' => 2],
            ['gross' => '50.50', 'precision' => 2],
            ['gross' => '33.333333', 'precision' => 6],
            ['gross' => '0.01', 'precision' => 2],
        ];

        foreach ($hundredPercentCases as $case) {
            $result = $this->calculator->extractTax($case['gross'], '100', $case['precision']);

            // مع ضريبة 100%: net = gross / 2
            $expectedNet = bcdiv($case['gross'], '2', $case['precision']);
            $this->assertEquals($expectedNet, $result['net']);
            $this->assertEquals($expectedNet, $result['tax']);
            $this->assertConditionNetPlusTaxEqualsGross($result, $case['precision']);
        }

        // 3. حالات ضريبة كسرية معقدة
        $complexCases = [
            ['gross' => '123.456789', 'rate' => '13.337', 'precision' => 6],
            ['gross' => '9876.54321', 'rate' => '7.7777', 'precision' => 8],
            ['gross' => '0.99999999', 'rate' => '19.9999', 'precision' => 10],
            ['gross' => '1000000.000001', 'rate' => '33.333333', 'precision' => 6],
        ];

        foreach ($complexCases as $case) {
            $result = $this->calculator->extractTax($case['gross'], $case['rate'], $case['precision']);
            $this->assertConditionNetPlusTaxEqualsGross($result, $case['precision']);
        }

        // 4. حالات دقة مختلفة لنفس القيم
        $sameValueCases = [
            '999.999999' => [2, 4, 6, 8],
            '1234.5678' => [0, 2, 4, 8],
            '0.0001' => [4, 6, 8, 10],
        ];

        foreach ($sameValueCases as $gross => $precisions) {
            foreach ($precisions as $precision) {
                $result = $this->calculator->extractTax($gross, '15.75', $precision);
                $this->assertConditionNetPlusTaxEqualsGross($result, $precision);
            }
        }
    }

    /**
     * اختبار 20 خدمة بمعدلات ضريبة مختلفة وأرقام صعبة جداً
     */
    public function testTwentyComplexServicesWithHardCalculations(): void
    {
        $services = $this->generateComplexServices(20);

        // حساب كل خدمة على حدة بجمع عالي الدقة
        $individualTotals = $this->calculateIndividualTotals($services, 8);

        // حساب المجموع باستخدام calculateBulk
        $bulkResult = $this->calculator->calculateBulk($services, 8);

        // التحقق من التطابق مع تسامح صغير جداً بسبب التدوير
        $this->assertEqualsWithPrecision(
            $individualTotals['net'],
            $bulkResult['net'],
            8,
            'مجموع Net الفردي لا يطابق المجموع الكلي'
        );

        $this->assertEqualsWithPrecision(
            $individualTotals['tax'],
            $bulkResult['tax'],
            8,
            'مجموع Tax الفردي لا يطابق المجموع الكلي'
        );

        $this->assertEqualsWithPrecision(
            $individualTotals['gross'],
            $bulkResult['gross'],
            8,
            'مجموع Gross الفردي لا يطابق المجموع الكلي'
        );

        // التحقق من المعادلة الأساسية في النتيجة المجمعة
        $this->assertConditionNetPlusTaxEqualsGross($bulkResult, 8);
    }

    /**
     * اختبار calculateBulk مع حالات متطرفة جداً
     */
    public function testBulkCalculationWithExtremeScenarios(): void
    {
        // سيناريو 1: 1000 عنصر بمبالغ متناهية الصغر ومعدلات مختلفة
        $items = [];
        for ($i = 1; $i <= 1000; $i++) {
            $items[] = [
                'price' => bcdiv('1', (string) ($i * 1000), 12),
                'tax_rate' => bcdiv($i, '10', 6)
            ];
        }

        $result = $this->calculator->calculateBulk($items, 12);
        $this->assertConditionNetPlusTaxEqualsGross($result, 12);

        // سيناريو 2: مبالغ كبيرة جداً بمعدلات ضريبة كسرية
        $largeItems = [
            ['price' => '9999999999.99999999', 'tax_rate' => '19.999999'],
            ['price' => '8888888888.88888888', 'tax_rate' => '7.777777'],
            ['price' => '7777777777.77777777', 'tax_rate' => '33.333333'],
            ['price' => '6666666666.66666666', 'tax_rate' => '0.000001'],
            ['price' => '5555555555.55555555', 'tax_rate' => '100'], // أقصى حد
        ];

        $result = $this->calculator->calculateBulk($largeItems, 8);
        $this->assertConditionNetPlusTaxEqualsGross($result, 8);

        // سيناريو 3: خليط من العناصر الصحيحة والخاطئة
        $mixedItems = [
            ['price' => '100.00', 'tax_rate' => '10'],
            ['tax_rate' => '15'], // بدون price
            ['price' => '200.00'], // بدون tax_rate
            [], // عنصر فارغ
            ['price' => '300.00', 'tax_rate' => '20'],
            ['price' => 'invalid', 'tax_rate' => '10'], // سيتم رفضها لاحقاً
        ];

        $result = $this->calculator->calculateBulk($mixedItems, 2);
        // يجب أن تحتوي فقط على العناصر الصحيحة: 100, 200, 300
        $expectedGross = bcadd('100.00', '200.00', 2);
        $expectedGross = bcadd($expectedGross, '300.00', 2);
        $this->assertEquals($expectedGross, $result['gross']);

        // سيناريو 4: عناصر بتنسيقات مختلفة للسعر
        $formattedItems = [
            ['price' => '1,000,000.50', 'tax_rate' => '15'],
            ['price' => '2 000 000.75', 'tax_rate' => '20'],
            ['price' => 3000000.25, 'tax_rate' => '25'], // float
            ['price' => '4.000.000,50', 'tax_rate' => '30'], // تنسيق أوروبي (سيفشل)
        ];

        $result = $this->calculator->calculateBulk($formattedItems, 2);
        // التحقق من أن العملية تمت دون أخطاء (باستثناء الأخير)
        $this->assertConditionNetPlusTaxEqualsGross($result, 2);
    }

    /**
     * اختبار الدقة في العمليات المتسلسلة والمجمعة
     */
    public function testPrecisionConsistencyInSerialVsBulk(): void
    {
        $items = $this->generateComplexServices(100);

        // طريقة 1: جمع النتائج المدورة فردياً
        $serialNet = '0';
        $serialTax = '0';
        $serialGross = '0';

        foreach ($items as $item) {
            $result = $this->calculator->extractTax($item['price'], $item['tax_rate'], 4);
            $serialNet = bcadd($serialNet, $result['net'], 10);
            $serialTax = bcadd($serialTax, $result['tax'], 10);
            $serialGross = bcadd($serialGross, $result['gross'], 10);
        }

        $serialNet = $this->bcRound($serialNet, 4);
        $serialTax = $this->bcRound($serialTax, 4);
        $serialGross = $this->bcRound($serialGross, 4);

        // طريقة 2: استخدام calculateBulk
        $bulkResult = $this->calculator->calculateBulk($items, 4);

        // يجب أن يكون الفرق أقل من 0.0001
        $netDiff = abs(bcsub($serialNet, $bulkResult['net'], 6));
        $taxDiff = abs(bcsub($serialTax, $bulkResult['tax'], 6));
        $grossDiff = abs(bcsub($serialGross, $bulkResult['gross'], 6));

        $this->assertLessThanOrEqual('0.0001', $netDiff,
            "الفرق في Net كبير جداً: {$netDiff}");
        $this->assertLessThanOrEqual('0.0001', $taxDiff,
            "الفرق في Tax كبير جداً: {$taxDiff}");
        $this->assertLessThanOrEqual('0.0001', $grossDiff,
            "الفرق في Gross كبير جداً: {$grossDiff}");
    }

    /**
     * اختبار حالات إدخال غير صالحة
     */
    public function testInvalidInputs(): void
    {
        // 1. نسبة ضريبة سالبة
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->extractTax('100', '-10', 2);

        // 2. نسبة ضريبة أكبر من 100%
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->extractTax('100', '101', 2);

        // 3. نسبة ضريبة غير رقمية
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->extractTax('100', 'abc', 2);

        // 4. مبلغ غير رقمي
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->extractTax('abc', '10', 2);

        // 5. دقة سالبة
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->extractTax('100', '10', -1);

        // 6. دقة كبيرة جداً
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->extractTax('100', '10', 11);
    }

    /**
     * اختبار الأداء مع عدد كبير جداً من العناصر
     */
    public function testPerformanceWithMassiveData(): void
    {
        $this->markTestSkipped('للتشغيل يدوياً فقط - يستغرق وقتاً طويلاً');

        $items = [];
        for ($i = 0; $i < 100000; $i++) { // 100,000 عنصر
            $items[] = [
                'price' => bcadd('10', bcdiv($i, '1000', 4), 4),
                'tax_rate' => bcdiv($i % 100, '1', 2)
            ];
        }

        $startTime = microtime(true);
        $result = $this->calculator->calculateBulk($items, 2);
        $endTime = microtime(true);

        $executionTime = $endTime - $startTime;

        // التحقق من الدقة أولاً
        $this->assertConditionNetPlusTaxEqualsGross($result, 2);

        // يجب أن يكتمل في وقت معقول (أقل من 5 ثواني)
        $this->assertLessThan(5, $executionTime,
            "الأداء ضعيف: {$executionTime} ثانية لـ 100,000 عنصر");

        echo "\nزمن التنفيذ لـ 100,000 عنصر: " . round($executionTime, 3) . " ثانية";
    }

    /**
     * اختبار خاص: مقارنة مع نماذج حسابية أخرى للتحقق من الدقة
     */
    public function testCrossValidationWithAlternativeCalculations(): void
    {
        // استخدام صيغ بديلة للتحقق
        $testCases = [
            ['gross' => '100', 'rate' => '20', 'precision' => 2],
            ['gross' => '123.45', 'rate' => '13.5', 'precision' => 4],
            ['gross' => '9999.9999', 'rate' => '7.7777', 'precision' => 8],
        ];

        foreach ($testCases as $case) {
            // الطريقة الأصلية
            $original = $this->calculator->extractTax(
                $case['gross'],
                $case['rate'],
                $case['precision']
            );

            // طريقة بديلة: net = gross / (1 + rate/100)
            $rateDecimal = bcdiv($case['rate'], '100', $case['precision'] + 4);
            $factor = bcadd('1', $rateDecimal, $case['precision'] + 4);
            $netAlt = bcdiv($case['gross'], $factor, $case['precision'] + 4);
            $netAlt = $this->bcRound($netAlt, $case['precision']);

            $taxAlt = bcsub($case['gross'], $netAlt, $case['precision'] + 4);
            $taxAlt = $this->bcRound($taxAlt, $case['precision']);

            // يجب أن تكون النتائج متطابقة
            $netDiff = abs(bcsub($original['net'], $netAlt, $case['precision'] + 2));
            $taxDiff = abs(bcsub($original['tax'], $taxAlt, $case['precision'] + 2));

            $this->assertLessThanOrEqual('0.01', $netDiff,
                "الفرق في Net كبير جداً: {$netDiff} للقيم {$case['gross']}, {$case['rate']}");

            $this->assertLessThanOrEqual('0.01', $taxDiff,
                "الفرق في Tax كبير جداً: {$taxDiff} للقيم {$case['gross']}, {$case['rate']}");
        }
    }

    /**
     * اختبار خاص: سيناريوهات أعمال حقيقية معقدة
     */
    public function testRealWorldBusinessScenarios(): void
    {
        // 1. فاتورة متجر إلكتروني مع خصومات وضرائب مختلفة
        $invoice = [
            ['price' => '2999.99', 'tax_rate' => '20'],   // لابتوب
            ['price' => '499.95', 'tax_rate' => '5'],     // كتب
            ['price' => '129.99', 'tax_rate' => '10'],    // ملابس
            ['price' => '29.99', 'tax_rate' => '0'],      // سلع معفاة
            ['price' => '899.99', 'tax_rate' => '20'],    // هاتف
            ['price' => '49.99', 'tax_rate' => '15'],     // إكسسوارات
            ['price' => '1999.99', 'tax_rate' => '18.75'], // تلفزيون
        ];

        $result = $this->calculator->calculateBulk($invoice, 2);

        // طباعة النتائج للفحص (يمكن تعليقها في الإنتاج)
        echo "\n=== فاتورة متجر إلكتروني ===\n";
        echo "المجموع الصافي: " . $result['net'] . "\n";
        echo "مجموع الضريبة: " . $result['tax'] . "\n";
        echo "المجموع الإجمالي: " . $result['gross'] . "\n";
        echo "التحقق: net + tax = " . bcadd($result['net'], $result['tax'], 2) . "\n";

        $this->assertConditionNetPlusTaxEqualsGross($result, 2);

        // 2. فاتورة مطعم مع إكراميات وضرائب مختلفة
        $restaurantBill = [
            ['price' => '45.50', 'tax_rate' => '10'],  // طعام
            ['price' => '12.75', 'tax_rate' => '10'],  // مشروبات
            ['price' => '8.99', 'tax_rate' => '0'],    // ماء معفى
            ['price' => '25.00', 'tax_rate' => '15'],  // حلويات
        ];

        $result = $this->calculator->calculateBulk($restaurantBill, 2);
        $this->assertConditionNetPlusTaxEqualsGross($result, 2);

        // 3. فاتورة مقسمة على بطاقات متعددة
        $splitPayment = array_merge(
            array_fill(0, 5, ['price' => '100.00', 'tax_rate' => '15']),
            array_fill(0, 3, ['price' => '50.00', 'tax_rate' => '10']),
            array_fill(0, 2, ['price' => '75.00', 'tax_rate' => '20'])
        );

        $result = $this->calculator->calculateBulk($splitPayment, 2);
        $this->assertConditionNetPlusTaxEqualsGross($result, 2);
    }

    /**
     * اختبار خاص: تتبع الأخطاء في التدوير
     */
    public function testRoundingErrorPropagation(): void
    {
        // اختبار خاص للكشف عن أخطاء التدوير المتراكمة
        $items = [];

        // إنشاء عناصر تسبب أخطاء تدوير
        for ($i = 0; $i < 100; $i++) {
            $items[] = [
                'price' => bcdiv('1.00', '3', 10), // 0.3333333333
                'tax_rate' => bcdiv('19', '3', 10) // 6.3333333333
            ];
        }

        $result = $this->calculator->calculateBulk($items, 2);

        // التحقق من عدم وجود أخطاء متراكمة كبيرة
        $calculatedGross = bcadd($result['net'], $result['tax'], 10);
        $diff = abs(bcsub($result['gross'], $calculatedGross, 10));

        $this->assertLessThanOrEqual('0.01', $diff,
            "خطأ تدوير متراكم كبير جداً: {$diff}");
    }

    // ========== الدوال المساعدة ==========

    /**
     * توليد خدمات معقدة للاختبار
     */
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

            // تأكد من أن معدل الضريبة بين 0 و 100
            if (bccomp($taxRate, '100', 6) > 0) {
                $taxRate = bcdiv($taxRate, '2', 6);
            }

            $services[] = [
                'price' => $price,
                'tax_rate' => $taxRate
            ];
        }

        return $services;
    }

    /**
     * حساب المجاميع الفردية
     */
    private function calculateIndividualTotals(array $services, int $precision): array
    {
        $totalNet = '0';
        $totalTax = '0';
        $totalGross = '0';

        foreach ($services as $service) {
            $result = $this->calculator->extractTax(
                $service['price'],
                $service['tax_rate'],
                $precision + 2 // دقة أعلى للتجميع
            );

            $totalNet = bcadd($totalNet, $result['net'], $precision + 4);
            $totalTax = bcadd($totalTax, $result['tax'], $precision + 4);
            $totalGross = bcadd($totalGross, $result['gross'], $precision + 4);
        }

        return [
            'net' => $this->bcRound($totalNet, $precision),
            'tax' => $this->bcRound($totalTax, $precision),
            'gross' => $this->bcRound($totalGross, $precision),
        ];
    }

    /**
     * التأكد من أن net + tax = gross
     */
    private function assertConditionNetPlusTaxEqualsGross(array $result, int $precision): void
    {
        $sum = bcadd($result['net'], $result['tax'], $precision);
        $this->assertEquals(
            $result['gross'],
            $sum,
            "شرط net + tax = gross فشل. net: {$result['net']}, tax: {$result['tax']}, gross: {$result['gross']}, sum: {$sum}"
        );
    }

    /**
     * مقارنة مع دقة معينة
     */
    private function assertEqualsWithPrecision(string $expected, string $actual, int $precision, string $message = ''): void
    {
        $expectedRounded = $this->bcRound($expected, $precision);
        $actualRounded = $this->bcRound($actual, $precision);

        $this->assertEquals($expectedRounded, $actualRounded, $message);
    }

    /**
     * تنسيق صفر بالدقة المطلوبة
     */
    private function formatZero(int $precision): string
    {
        return $precision > 0
            ? '0.' . str_repeat('0', $precision)
            : '0';
    }

    /**
     * تدوير رقم (نسخة من الدالة في السيرفيس)
     */
    private function bcRound(string $number, int $precision): string
    {
        if ($precision < 0) {
            throw new InvalidArgumentException('Precision must be >= 0');
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
}
