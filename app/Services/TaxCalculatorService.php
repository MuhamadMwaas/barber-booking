<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * خدمة حساب الضرائب بدقة عالية — المصدر الوحيد لحساب الضريبة في المشروع كله.
 *
 * الأسعار في النظام كلها GROSS (شاملة الضريبة)، فالضريبة تُستخرج عكسياً
 * ولا تُضاف أبداً. انظر Agent.md §12.1.
 *
 * ═══════════════════════════════════════════════════════════════════════
 * القاعدة الحاكمة (MON-01): احسب بدقة أعلى بكثير مما تحتاج، وقرّب مرة
 * واحدة فقط في النهاية.
 * ═══════════════════════════════════════════════════════════════════════
 *
 * `bcdiv` في PHP **تقتطع (truncate) ولا تقرّب**. القسمة بدقة 2 مباشرة تفقد
 * الخانات التي كان يجب أن تُقرَّب، وتقريبُ رقمٍ مقرَّبٍ مسبقاً لا يُعيدها:
 *
 *     50.00 ÷ 1.19 = 42.016806722689...
 *     bcdiv(..., 2)  → "42.01"   ← اقتطاع، والضريبة تصير 7.99 (خطأ)
 *     التقريب الصحيح → 42.02     ← والضريبة 7.98 (صحيح)
 *
 * ولهذا لا تظهر أي دقة ثابتة في هذا الملف: كل عملية وسيطة تجري على
 * internalScale($precision) — أوسع من الدقة المطلوبة بثماني خانات على
 * الأقل — والتقريب يحدث في النهاية فقط.
 *
 * ⚠️ لا تستخدم `bcscale()` هنا أبداً: إنها حالة عامة على مستوى الطلب
 *    كله، فتغيّر بصمت سلوك كل عملية bcmath في بقية الطلب (MON-06).
 *    مرّر الدقة صريحةً كوسيط ثالث لكل نداء bcmath.
 */
class TaxCalculatorService
{
    /**
     * الحد الأدنى للدقة الداخلية. الدقة الفعلية دائماً أوسع من الدقة
     * المطلوبة بثماني خانات، فلا يتأثر الناتج باقتطاع bcdiv.
     */
    private const MIN_INTERNAL_SCALE = 10;

    /** الحد الأقصى للدقة المطلوبة. النقود تحتاج 2؛ ما بعد 12 لا معنى له. */
    private const MAX_PRECISION = 12;

    /**
     * فصل الضريبة من المبلغ الإجمالي (حساب عكسي).
     *
     * يضمن دائماً: net + tax = gross بالدقة المطلوبة، بلا فرق سنت.
     *
     * @param string|float $grossAmount المبلغ الإجمالي (شامل الضريبة)
     * @param string|float $taxRate نسبة الضريبة (مثال: 19 أو 19.5 للدلالة على 19.5%)
     * @param int $precision عدد الخانات العشرية للنتيجة (افتراضي: 2)
     * @return array{net: string, tax: string, gross: string}
     * @throws InvalidArgumentException
     */
    public function extractTax($grossAmount, $taxRate, int $precision = 2): array
    {
        $this->validateInputs($grossAmount, $taxRate, $precision);

        $scale = $this->internalScale($precision);
        $gross = $this->normalizeAmount($grossAmount, $scale);
        $rate  = $this->normalizeAmount($taxRate, $scale);

        // ضريبة صفرية: الصافي = الإجمالي، ولا شيء لاستخراجه.
        if (bccomp($rate, '0', $scale) === 0) {
            $net = $this->bcRound($gross, $precision);

            return [
                'net'   => $net,
                'tax'   => $this->formatZero($precision),
                'gross' => $net,
            ];
        }

        // كل الخطوات الوسيطة بالدقة الداخلية الواسعة.
        // factor = 1 + (rate / 100) — بدقة كافية لئلا تُقتطع النِسَب الكسرية:
        // bcdiv('19.5', '100', 2) كانت تُنتج '0.19' فتُدمَّر الخانة الثانية.
        $factor  = bcadd('1', bcdiv($rate, '100', $scale), $scale);
        $netHigh = bcdiv($gross, $factor, $scale);
        $taxHigh = bcsub($gross, $netHigh, $scale);

        // التقريب في النهاية فقط.
        $net          = $this->bcRound($netHigh, $precision);
        $tax          = $this->bcRound($taxHigh, $precision);
        $grossRounded = $this->bcRound($gross, $precision);

        return $this->reconcile($net, $tax, $grossRounded, $precision);
    }

    /**
     * إضافة الضريبة إلى مبلغ صافٍ (حساب أمامي).
     *
     * ⚠️ هذه العملية **ليست** عكس extractTax: الاستخراج العكسي دالة غير
     *    عكوسة، فعدة قيم gross تنتج نفس الـ net المقرَّب. مثال بنسبة 19%:
     *
     *        45.00 ÷ 1.19 = 37.8151…  →  net يُقرَّب إلى 37.82
     *        37.82 × 1.19 = 45.0058   →  gross يُقرَّب إلى 45.01  ≠ 45.00
     *
     *    فلا تستعملها أبداً لإعادة بناء gross كان مخزَّناً أصلاً — ستفقد
     *    سنتاً. الـ gross هو الحقيقة ويجب تخزينه. انظر InvoiceItem::calculateTotal().
     *
     * @param string|float $netAmount المبلغ الصافي (قبل الضريبة)
     * @param string|float $taxRate نسبة الضريبة
     * @param int $precision عدد الخانات العشرية
     * @return array{net: string, tax: string, gross: string}
     * @throws InvalidArgumentException
     */
    public function addTax($netAmount, $taxRate, int $precision = 2): array
    {
        $this->validateInputs($netAmount, $taxRate, $precision);

        $scale = $this->internalScale($precision);
        $net   = $this->normalizeAmount($netAmount, $scale);
        $rate  = $this->normalizeAmount($taxRate, $scale);

        if (bccomp($rate, '0', $scale) === 0) {
            $netRounded = $this->bcRound($net, $precision);

            return [
                'net'   => $netRounded,
                'tax'   => $this->formatZero($precision),
                'gross' => $netRounded,
            ];
        }

        // tax = net × (rate / 100)  —  gross = net + tax
        $taxHigh   = bcmul($net, bcdiv($rate, '100', $scale), $scale);
        $grossHigh = bcadd($net, $taxHigh, $scale);

        $netRounded   = $this->bcRound($net, $precision);
        $tax          = $this->bcRound($taxHigh, $precision);
        $grossRounded = $this->bcRound($grossHigh, $precision);

        return $this->reconcile($netRounded, $tax, $grossRounded, $precision);
    }

    /**
     * حساب إجماليات مجموعة من العناصر.
     *
     * التقريب يحدث **لكل بند** بالدقة المطلوبة (وهذا ما تقتضيه الممارسة
     * المحاسبية للفواتير: البند المطبوع يجب أن يساوي البند المحسوب)، ثم
     * تُجمَّع البنود بالدقة الداخلية الواسعة وتُقرَّب مرة أخيرة.
     *
     * العناصر التي لا تحمل سعراً رقمياً صالحاً تُتجاهل بصمت — الاستدعاء
     * قد يأتي من مصفوفة نموذج فيها صفوف فارغة أو نصف مكتملة.
     *
     * @param array<array{price?: mixed, tax_rate?: mixed}> $items
     * @return array{net: string, tax: string, gross: string}
     */
    public function calculateBulk(array $items, int $precision = 2): array
    {
        if ($precision < 0 || $precision > self::MAX_PRECISION) {
            throw new InvalidArgumentException(
                'Precision must be between 0 and ' . self::MAX_PRECISION
            );
        }

        $scale = $this->internalScale($precision);

        $totalNet   = '0';
        $totalTax   = '0';
        $totalGross = '0';

        foreach ($items as $item) {
            if (! isset($item['price']) || ! $this->isUsableAmount($item['price'])) {
                continue;
            }

            $taxRate = $item['tax_rate'] ?? '0';
            if (! $this->isUsableAmount($taxRate)) {
                continue;
            }

            // بالدقة المطلوبة لكل بند — لا بدقة ثابتة كما كان سابقاً.
            $result = $this->extractTax($item['price'], $taxRate, $precision);

            $totalNet   = bcadd($totalNet, $result['net'], $scale);
            $totalTax   = bcadd($totalTax, $result['tax'], $scale);
            $totalGross = bcadd($totalGross, $result['gross'], $scale);
        }

        return $this->reconcile(
            $this->bcRound($totalNet, $precision),
            $this->bcRound($totalTax, $precision),
            $this->bcRound($totalGross, $precision),
            $precision
        );
    }

    /**
     * فرض التطابق net + tax = gross.
     *
     * قرار محاسبي: الفرق يُضاف إلى **الضريبة دائماً**، لا إلى «القيمة
     * الأكبر» كما كان سابقاً. الصافي هو الأساس الذي يُبنى عليه سعر الخدمة،
     * والضريبة مشتقة منه — فتعديلها هو التصرف الصحيح. والأهم أنه **حتمي**:
     * السلوك القديم كان يعدّل الصافي أحياناً والضريبة أحياناً حسب النسبة،
     * فينتج أرقاماً لا يمكن التنبؤ بها ولا مطابقتها مع طبقة أخرى.
     *
     * @return array{net: string, tax: string, gross: string}
     */
    private function reconcile(string $net, string $tax, string $gross, int $precision): array
    {
        $diff = bcsub($gross, bcadd($net, $tax, $precision), $precision);

        if (bccomp($diff, '0', $precision) !== 0) {
            $tax = bcadd($tax, $diff, $precision);
        }

        return [
            'net'   => $net,
            'tax'   => $tax,
            'gross' => $gross,
        ];
    }

    /**
     * الدقة الداخلية لحسابٍ ناتجه بدقة $precision.
     *
     * أوسع من المطلوب بثماني خانات على الأقل، فاقتطاع bcdiv يقع بعيداً
     * جداً عن الخانة التي تُقرَّب.
     */
    private function internalScale(int $precision): int
    {
        return max(self::MIN_INTERNAL_SCALE, $precision + 8);
    }

    /**
     * تدوير رقم bcmath تدويراً حسابياً (half-up) بعيداً عن الصفر.
     */
    private function bcRound(string $number, int $precision): string
    {
        if ($precision < 0) {
            throw new InvalidArgumentException('Precision must be >= 0');
        }

        $sign = '';
        if (str_starts_with($number, '-')) {
            $sign   = '-';
            $number = substr($number, 1);
        }

        $shift   = '1' . str_repeat('0', $precision);
        $shifted = bcmul($number, $shift, $precision + 6);
        $rounded = bcadd($shifted, '0.5', $precision + 6);
        $floored = bcdiv($rounded, '1', 0);
        $result  = bcdiv($floored, $shift, $precision);

        return $sign . $result;
    }

    /**
     * تطبيع المبلغ إلى string صالح لـ bcmath.
     *
     * ⚠️ الدقة تُمرَّر صريحةً: النسخة القديمة كانت تُطبِّع بـ
     *    bcadd($amount, '0', 2) فتقتطع **المدخل نفسه** إلى منزلتين قبل أي
     *    حساب — فمبلغ '33.333333' كان يصل إلى المعادلة كـ '33.33'.
     */
    private function normalizeAmount($amount, int $scale): string
    {
        if (is_string($amount)) {
            $amount = trim($amount);
        } elseif (is_numeric($amount)) {
            $amount = $this->numericToString($amount);
        } else {
            throw new InvalidArgumentException('Amount must be numeric');
        }

        // إزالة فواصل الآلاف والمسافات
        $amount = str_replace([',', ' '], '', $amount);

        if (! is_numeric($amount)) {
            throw new InvalidArgumentException('Invalid numeric value: ' . $amount);
        }

        return bcadd($this->numericToString($amount), '0', $scale);
    }

    /**
     * تحويل رقم إلى string بلا صيغة أسّية.
     *
     * (string) 1.0E-7 يُنتج "1.0E-7" وهي غير صالحة لـ bcmath — والمبالغ
     * الصغيرة جداً تصل بهذه الصيغة من حسابات float.
     */
    private function numericToString($value): string
    {
        if (is_string($value) && stripos($value, 'e') === false) {
            return $value;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        // 18 خانة تغطي أي مبلغ واقعي بلا فقدان معلومة.
        return rtrim(rtrim(number_format((float) $value, 18, '.', ''), '0'), '.') ?: '0';
    }

    /**
     * هل هذه القيمة قابلة للاستخدام كمبلغ؟ (لتصفية صفوف النماذج الناقصة)
     */
    private function isUsableAmount($value): bool
    {
        if (is_int($value) || is_float($value)) {
            return true;
        }

        if (! is_string($value)) {
            return false;
        }

        return is_numeric(str_replace([',', ' '], '', trim($value)));
    }

    /**
     * التحقق من صحة المدخلات
     */
    private function validateInputs($amount, $taxRate, int $precision): void
    {
        if ($precision < 0 || $precision > self::MAX_PRECISION) {
            throw new InvalidArgumentException(
                'Precision must be between 0 and ' . self::MAX_PRECISION
            );
        }

        $scale          = $this->internalScale($precision);
        $normalizedRate = $this->normalizeAmount($taxRate, $scale);

        if (bccomp($normalizedRate, '0', $scale) === -1) {
            throw new InvalidArgumentException('Tax rate cannot be negative');
        }

        if (bccomp($normalizedRate, '100', $scale) === 1) {
            throw new InvalidArgumentException('Tax rate cannot exceed 100%');
        }
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
}
