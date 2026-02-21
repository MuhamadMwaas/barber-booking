<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * خدمة حساب الضرائب بدقة عالية
 *
 * تستخدم bcmath لتجنب مشاكل Float
 * المبالغ المدخلة والمخرجة تكون مع الضريبة (gross)
 */
class TaxCalculatorService
{
    private int $scale = 2; // الدقة الداخلية

    /**
     * فصل الضريبة من المبلغ الإجمالي
     *
     * @param string|float $grossAmount المبلغ الإجمالي (مع الضريبة)
     * @param string|float $taxRate نسبة الضريبة (مثال: 19 للدلالة على 19%)
     * @param int $precision عدد الخانات العشرية للنتيجة (افتراضي: 2)
     * @return array ['net' => string, 'tax' => string, 'gross' => string]
     * @throws InvalidArgumentException
     */
    public function extractTax($grossAmount, $taxRate, int $precision = 2): array
    {
        // التحقق من المدخلات
        $this->validateInputs($grossAmount, $taxRate, $precision);

        // تحويل إلى strings
        $gross = $this->normalizeAmount($grossAmount);
        $rate = $this->normalizeAmount($taxRate);

        bcscale($this->scale);

        // إذا كانت الضريبة = 0
        if (bccomp($rate, '0', $this->scale) === 0) {
            $net = $this->bcRound($gross, $precision);
            return [
                'net' => $net,
                'tax' => $this->formatZero($precision),
                'gross' => $net,
            ];
        }

        // حساب معامل الضريبة: factor = 1 + (rate / 100)
        $factor = bcadd('1', bcdiv($rate, '100', $this->scale), $this->scale);

        // صافي المبلغ: net = gross / factor
        $netHigh = bcdiv($gross, $factor, $this->scale);

        // قيمة الضريبة: tax = gross - net
        $taxHigh = bcsub($gross, $netHigh, $this->scale);

        // التدوير للدقة المطلوبة
        $net = $this->bcRound($netHigh, $precision);
        $tax = $this->bcRound($taxHigh, $precision);
        $grossRounded = $this->bcRound($gross, $precision);

        // التحقق من المطابقة: net + tax = gross
        bcscale($precision);
        $sum = bcadd($net, $tax, $precision);
        $diff = bcsub($grossRounded, $sum, $precision);

        // إذا كان هناك فرق بسيط، نعدله
        if (bccomp($diff, '0', $precision) !== 0) {
            // نضيف الفرق للقيمة الأكبر
            if (bccomp($net, $tax, $precision) >= 0) {
                $net = bcadd($net, $diff, $precision);
            } else {
                $tax = bcadd($tax, $diff, $precision);
            }
        }

        return [
            'net' => $net,
            'tax' => $tax,
            'gross' => $grossRounded,
        ];
    }

    /**
     * حساب الضريبة وإضافتها للمبلغ (العملية العكسية)
     *
     * @param string|float $netAmount المبلغ الصافي (قبل الضريبة)
     * @param string|float $taxRate نسبة الضريبة
     * @param int $precision عدد الخانات العشرية
     * @return array ['net' => string, 'tax' => string, 'gross' => string]
     */
    public function addTax($netAmount, $taxRate, int $precision = 2): array
    {
        $this->validateInputs($netAmount, $taxRate, $precision);

        $net = $this->normalizeAmount($netAmount);
        $rate = $this->normalizeAmount($taxRate);

        bcscale($this->scale);

        if (bccomp($rate, '0', $this->scale) === 0) {
            $netRounded = $this->bcRound($net, $precision);
            return [
                'net' => $netRounded,
                'tax' => $this->formatZero($precision),
                'gross' => $netRounded,
            ];
        }

        // tax = net * (rate / 100)
        $taxHigh = bcmul($net, bcdiv($rate, '100', $this->scale), $this->scale);

        // gross = net + tax
        $grossHigh = bcadd($net, $taxHigh, $this->scale);

        $netRounded = $this->bcRound($net, $precision);
        $tax = $this->bcRound($taxHigh, $precision);
        $gross = $this->bcRound($grossHigh, $precision);

        // التحقق من المطابقة
        bcscale($precision);
        $sum = bcadd($netRounded, $tax, $precision);
        $diff = bcsub($gross, $sum, $precision);

        if (bccomp($diff, '0', $precision) !== 0) {
            $tax = bcadd($tax, $diff, $precision);
        }

        return [
            'net' => $netRounded,
            'tax' => $tax,
            'gross' => $gross,
        ];
    }

    /**
     * حساب إجماليات مجموعة من العناصر
     *
     * @param array $items مصفوفة من العناصر، كل عنصر يحتوي على 'price' و 'tax_rate'
     * @param int $precision
     * @return array
     */
    public function calculateBulk(array $items, int $precision = 2): array
    {
        bcscale($this->scale);

        $totalNet = '0';
        $totalTax = '0';
        $totalGross = '0';

        foreach ($items as $item) {
            if (!isset($item['price'])) {
                continue;
            }

            $taxRate = $item['tax_rate'] ?? '0';
            $result = $this->extractTax($item['price'], $taxRate, $this->scale);

            $totalNet = bcadd($totalNet, $result['net'], $this->scale);
            $totalTax = bcadd($totalTax, $result['tax'], $this->scale);
            $totalGross = bcadd($totalGross, $result['gross'], $this->scale);
        }

        // التدوير النهائي
        $net = $this->bcRound($totalNet, $precision);
        $tax = $this->bcRound($totalTax, $precision);
        $gross = $this->bcRound($totalGross, $precision);

        // مطابقة نهائية
        bcscale($precision);
        $sum = bcadd($net, $tax, $precision);
        $diff = bcsub($gross, $sum, $precision);

        if (bccomp($diff, '0', $precision) !== 0) {
            if (bccomp($net, $tax, $precision) >= 0) {
                $net = bcadd($net, $diff, $precision);
            } else {
                $tax = bcadd($tax, $diff, $precision);
            }
        }

        return [
            'net' => $net,
            'tax' => $tax,
            'gross' => $gross,
        ];
    }

    /**
     * تدوير رقم bcmath بشكل صحيح
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

    /**
     * تطبيع المبلغ إلى string صالح لـ bcmath
     */
    private function normalizeAmount($amount): string
    {
        if (is_string($amount)) {
            $amount = trim($amount);
        } elseif (is_numeric($amount)) {
            $amount = (string) $amount;
        } else {
            throw new InvalidArgumentException('Amount must be numeric');
        }

        // إزالة الفواصل والمسافات
        $amount = str_replace([',', ' '], '', $amount);

        // التحقق من صحة الرقم
        if (!is_numeric($amount)) {
            throw new InvalidArgumentException('Invalid numeric value: ' . $amount);
        }

        return bcadd($amount, '0', $this->scale);
    }

    /**
     * التحقق من صحة المدخلات
     */
    private function validateInputs($amount, $taxRate, int $precision): void
    {
        if ($precision < 0 || $precision > 10) {
            throw new InvalidArgumentException('Precision must be between 0 and 10');
        }

        $normalizedRate = $this->normalizeAmount($taxRate);
        if (bccomp($normalizedRate, '0', $this->scale) === -1) {
            throw new InvalidArgumentException('Tax rate cannot be negative');
        }

        if (bccomp($normalizedRate, '100', $this->scale) === 1) {
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

