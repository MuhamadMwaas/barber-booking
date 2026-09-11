<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\TaxCalculatorService;
use Illuminate\Console\Command;

/**
 * تقرير انحراف الضريبة — MON-01.
 *
 * ═══════════════════════════════════════════════════════════════════════
 * ⚠️ للقراءة فقط. لا يُنفّذ UPDATE ولا INSERT ولا DELETE. أبداً.
 * ═══════════════════════════════════════════════════════════════════════
 *
 * الفواتير التي أُنهيت قبل إصلاح MON-01 تحمل ضريبة محسوبة بالتنفيذ المعطوب
 * (قسمة bcmath بدقة 2 تقتطع ولا تقرّب)، فتنحرف بسنت عن القيمة الصحيحة في
 * معظم الأسعار.
 *
 * ولا يجوز تصحيحها بأثر رجعي: مبدأ **Unveränderbarkeit** في GoBD الألماني
 * (§146 AO و§14 UStG) يمنع تعديل مستند محاسبي صدر للعميل. الفاتورة المطبوعة
 * التي بيد الزبون تحمل الرقم القديم، وتغييرُ الصف في قاعدة البيانات يجعل
 * السجل يخالف المستند — وهذا أسوأ بكثير من فرق سنت، لأنه يحوّل خطأً حسابياً
 * قابلاً للتفسير إلى تعارضٍ بين الدفاتر والمستندات لا يمكن تفسيره لمدقّق.
 *
 * فالغرض من هذا الأمر أن **تعرف الحجم** لا أن تُخفيه: كم فاتورة، وما مجموع
 * الانحراف، حتى تُدرجه في بيان تصحيحي إن لزم — بالتشاور مع محاسبك.
 *
 * الإصلاح يسري على كل ما يُنشأ من الآن فصاعداً تلقائياً.
 */
class TaxDriftReport extends Command
{
    protected $signature = 'tax:drift-report
                            {--status=all : all|paid|draft — أي حالات الفواتير تُفحَص}
                            {--limit=50 : كم صفاً منحرفاً يُعرَض في الجدول}
                            {--csv= : مسار ملف CSV لكتابة التقرير الكامل}';

    protected $description = 'يعدّ الفواتير التي تحمل ضريبة محسوبة بالتنفيذ المعطوب (MON-01). قراءة فقط — لا يعدّل شيئاً.';

    public function handle(TaxCalculatorService $tax): int
    {
        $this->components->info('MON-01 — تقرير انحراف ضريبة القيمة المضافة');
        $this->line('  <fg=yellow>قراءة فقط: هذا الأمر لا يعدّل أي صف.</>');
        $this->newLine();

        $query = Invoice::query()->select([
            'id', 'invoice_number', 'status', 'tax_rate',
            'subtotal', 'tax_amount', 'total_amount', 'created_at',
        ]);

        match ($this->option('status')) {
            'paid'  => $query->where('status', \App\Enum\InvoiceStatus::PAID),
            'draft' => $query->where('status', \App\Enum\InvoiceStatus::DRAFT),
            default => null,
        };

        $scanned   = 0;
        $drifted   = 0;
        $totalNetDrift = '0.00';
        $totalTaxDrift = '0.00';
        $rows      = [];
        $csvRows   = [];

        $query->orderBy('id')->chunk(500, function ($invoices) use (
            $tax, &$scanned, &$drifted, &$totalNetDrift, &$totalTaxDrift, &$rows, &$csvRows
        ) {
            foreach ($invoices as $invoice) {
                $scanned++;

                $gross = (string) $invoice->total_amount;
                $rate  = (string) ($invoice->tax_rate ?? 0);

                if (! is_numeric($gross) || ! is_numeric($rate)) {
                    continue;
                }

                // ما كان يجب أن يُكتب، بالحاسبة المُصلَحة.
                $correct = $tax->extractTax($gross, $rate, 2);

                $netDrift = bcsub((string) $invoice->subtotal, $correct['net'], 2);
                $taxDrift = bcsub((string) $invoice->tax_amount, $correct['tax'], 2);

                if (bccomp($netDrift, '0.00', 2) === 0 && bccomp($taxDrift, '0.00', 2) === 0) {
                    continue;
                }

                $drifted++;
                $totalNetDrift = bcadd($totalNetDrift, $netDrift, 2);
                $totalTaxDrift = bcadd($totalTaxDrift, $taxDrift, 2);

                $row = [
                    $invoice->id,
                    $invoice->invoice_number ?? '(draft)',
                    $invoice->status?->name ?? '?',
                    $gross,
                    (string) $invoice->tax_amount,
                    $correct['tax'],
                    $taxDrift,
                    $invoice->created_at?->format('Y-m-d') ?? '',
                ];

                $csvRows[] = $row;

                if (count($rows) < (int) $this->option('limit')) {
                    $rows[] = $row;
                }
            }
        });

        if ($drifted === 0) {
            $this->components->info("تم فحص {$scanned} فاتورة — لا انحراف. كل الصفوف متوافقة مع الحاسبة المُصلَحة.");

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'رقم الفاتورة', 'الحالة', 'الإجمالي', 'الضريبة المخزَّنة', 'الصحيحة', 'الانحراف', 'التاريخ'],
            $rows
        );

        if ($drifted > count($rows)) {
            $this->line('  … و' . ($drifted - count($rows)) . ' صفاً آخر (استخدم <fg=cyan>--limit</> أو <fg=cyan>--csv</>).');
        }

        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>فواتير مفحوصة</>', (string) $scanned);
        $this->components->twoColumnDetail('<fg=gray>فواتير منحرفة</>', "<fg=yellow>{$drifted}</>");
        $this->components->twoColumnDetail('<fg=gray>مجموع انحراف الضريبة</>', "<fg=yellow>{$totalTaxDrift}</>");
        $this->components->twoColumnDetail('<fg=gray>مجموع انحراف الصافي</>', "<fg=yellow>{$totalNetDrift}</>");
        $this->newLine();

        if ($path = $this->option('csv')) {
            $handle = fopen($path, 'w');
            fputcsv($handle, ['invoice_id', 'invoice_number', 'status', 'total_amount',
                'stored_tax', 'correct_tax', 'tax_drift', 'created_at']);
            foreach ($csvRows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);

            $this->components->info("كُتب التقرير الكامل إلى: {$path}");
        }

        $this->line('  <fg=yellow>لم يُعدَّل شيء.</> الفواتير المُنهاة لا تُصحَّح بأثر رجعي (GoBD');
        $this->line('  Unveränderbarkeit) — استخدم هذه الأرقام مع محاسبك، لا لتعديل الصفوف.');

        return self::SUCCESS;
    }
}
