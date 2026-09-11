<?php

namespace App\Console\Commands;

use App\Enum\InvoiceStatus;
use App\Services\DocumentNumberGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تشخيص أرقام المستندات — MON-03 / DB-01.
 *
 * ═══════════════════════════════════════════════════════════════════════
 * ⚠️ للقراءة فقط. لا UPDATE ولا INSERT ولا DELETE. أبداً.
 * ═══════════════════════════════════════════════════════════════════════
 *
 * شغّله **قبل** الترحيل لترى ما ستمسّه المايقريشن، و**بعده** للتأكد أن
 * الفرادة صارت مفروضة فعلاً.
 *
 * الـ migration نفسها تُصلح وتُبلِّغ، لكن الإصلاح على بيانات محاسبية حقيقية
 * لا يجوز أن يكون أول مرة تعرف فيها الحجم.
 */
class DocumentNumberAudit extends Command
{
    protected $signature = 'documents:number-audit
                            {--csv= : مسار ملف CSV للتقرير الكامل}';

    protected $description = 'يفحص تكرار أرقام الفواتير والمدفوعات والمواعيد وحالة القيود الفريدة (MON-03). قراءة فقط.';

    public function handle(): int
    {
        $this->components->info('MON-03 — تشخيص أرقام المستندات المالية');
        $this->line('  <fg=yellow>قراءة فقط: هذا الأمر لا يعدّل أي صف.</>');
        $this->newLine();

        $rows = [];
        $problems = 0;

        // ── 1. أرقام مكررة ────────────────────────────────────────────────
        foreach ([
            ['invoices', 'invoice_number', 'رقم الفاتورة'],
            ['payments', 'payment_number', 'رقم الدفع'],
            ['appointments', 'number', 'رقم الموعد'],
        ] as [$table, $column, $label]) {
            $dupes = DB::table($table)
                ->select($column, DB::raw('COUNT(*) as c'))
                ->whereNotNull($column)
                ->groupBy($column)
                ->havingRaw('COUNT(*) > 1')
                ->orderByDesc('c')
                ->get();

            $affected = (int) $dupes->sum('c');
            $problems += $dupes->count();

            $this->components->twoColumnDetail(
                "<fg=gray>{$label} — قيم مكررة</>",
                $dupes->isEmpty()
                    ? '<fg=green>لا شيء</>'
                    : "<fg=red>{$dupes->count()} قيمة تغطي {$affected} صفاً</>"
            );

            foreach ($dupes->take(10) as $d) {
                $rows[] = [$table, $column, $d->$column, $d->c];
            }
        }

        // ── 2. مواعيد بأكثر من فاتورة ─────────────────────────────────────
        $multi = DB::table('invoices')
            ->select('appointment_id', DB::raw('COUNT(*) as c'))
            ->whereNotNull('appointment_id')
            ->groupBy('appointment_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $problems += $multi->count();

        $this->components->twoColumnDetail(
            '<fg=gray>مواعيد بأكثر من فاتورة</>',
            $multi->isEmpty() ? '<fg=green>لا شيء</>' : "<fg=red>{$multi->count()}</>"
        );

        // الحالة الوحيدة التي ترفض المايقريشن أن تعالجها تلقائياً.
        $blocking = [];
        foreach ($multi as $m) {
            $finalized = DB::table('invoices')
                ->where('appointment_id', $m->appointment_id)
                ->where('status', '!=', InvoiceStatus::DRAFT->value)
                ->count();

            if ($finalized > 1) {
                $blocking[] = $m->appointment_id;
            }
            $rows[] = ['invoices', 'appointment_id', (string) $m->appointment_id, $m->c];
        }

        // ── 3. أزواج provider_service مكررة ───────────────────────────────
        if (Schema::hasTable('provider_service')) {
            $pairs = DB::table('provider_service')
                ->select('provider_id', 'service_id', DB::raw('COUNT(*) as c'))
                ->groupBy('provider_id', 'service_id')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            $problems += $pairs->count();

            $this->components->twoColumnDetail(
                '<fg=gray>أزواج provider_service مكررة</>',
                $pairs->isEmpty() ? '<fg=green>لا شيء</>' : "<fg=red>{$pairs->count()}</>"
            );

            foreach ($pairs as $p) {
                $rows[] = ['provider_service', 'provider_id+service_id',
                    "{$p->provider_id}+{$p->service_id}", $p->c];
            }
        }

        // ── 4. حالة القيود الفريدة ────────────────────────────────────────
        $this->newLine();
        $this->line('  <options=bold>القيود الفريدة</>');

        foreach ([
            ['invoices', 'invoices_number_unique'],
            ['invoices', 'invoices_appointment_unique'],
            ['payments', 'payments_number_unique'],
            ['appointments', 'appointments_number_unique'],
            ['provider_service', 'provider_service_unique'],
        ] as [$table, $index]) {
            $this->components->twoColumnDetail(
                "<fg=gray>{$index}</>",
                $this->hasIndex($table, $index) ? '<fg=green>مفروض</>' : '<fg=red>مفقود</>'
            );
        }

        // ── 5. حالة العدّادات ─────────────────────────────────────────────
        $this->newLine();
        $this->line('  <options=bold>العدّادات</>');

        if (! Schema::hasTable('document_counters')) {
            $this->components->twoColumnDetail(
                '<fg=gray>document_counters</>',
                '<fg=red>الجدول مفقود — شغّل php artisan migrate</>'
            );
        } else {
            foreach (['invoice', 'payment'] as $series) {
                $c = DocumentNumberGenerator::peek($series);
                $this->components->twoColumnDetail(
                    "<fg=gray>{$series}</>",
                    $c['period'] === null
                        ? '<fg=red>الصف مفقود</>'
                        : "period {$c['period']}، آخر رقم {$c['current']}"
                );
            }
        }

        // ── الخلاصة ───────────────────────────────────────────────────────
        $this->newLine();

        if ($rows) {
            $this->table(['الجدول', 'العمود', 'القيمة', 'عدد الصفوف'], $rows);
        }

        if ($path = $this->option('csv')) {
            $handle = fopen($path, 'w');
            fputcsv($handle, ['table', 'column', 'value', 'row_count']);
            foreach ($rows as $r) {
                fputcsv($handle, $r);
            }
            fclose($handle);
            $this->components->info("كُتب التقرير إلى: {$path}");
        }

        if ($blocking) {
            $this->components->error(
                'مواعيد تحمل أكثر من فاتورة **مُنهاة**: ' . implode(', ', $blocking) . '. '
                . 'المايقريشن ترفض حذف مستند صادر — قرِّر مع محاسبك أي فاتورة تبقى، '
                . 'وألغِ الأخرى (status = CANCELLED) أو افصلها عن الموعد، ثم أعد الترحيل.'
            );

            return self::FAILURE;
        }

        if ($problems === 0) {
            $this->components->info('لا تكرارات. الفرادة سليمة.');
        } else {
            $this->components->warn(
                "{$problems} مشكلة فرادة. `php artisan migrate` سيُصلحها ويوثّق كل تغيير "
                . 'في invoice_data.number_correction ثم يفرض القيود.'
            );
        }

        $this->line('  <fg=yellow>لم يُعدَّل شيء.</>');

        return self::SUCCESS;
    }

    private function hasIndex(string $table, string $index): bool
    {
        try {
            if (! Schema::hasTable($table)) {
                return false;
            }

            return collect(Schema::getConnection()->getSchemaBuilder()->getIndexes($table))
                ->contains(fn ($i) => ($i['name'] ?? null) === $index);
        } catch (\Throwable) {
            return false;
        }
    }
}
