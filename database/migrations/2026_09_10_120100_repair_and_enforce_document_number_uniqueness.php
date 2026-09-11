<?php

use App\Enum\InvoiceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * إصلاح التكرارات القائمة ثم فرض الفرادة — MON-03 / DB-01.
 *
 * ════════════════════════════════════════════════════════════════════════
 * لماذا الإصلاح والقيد في مايقريشن واحدة؟
 * ════════════════════════════════════════════════════════════════════════
 *
 * لا يمكن إضافة `UNIQUE` على عمود فيه تكرارات — ستفشل الـ migration. وبين
 * تنظيف التكرارات وإضافة القيد توجد نافذة يمكن أن تُنتج فيها تكراراتٌ جديدة.
 * فالخطوتان معاً، بهذا الترتيب، في معاملة واحدة.
 *
 * ════════════════════════════════════════════════════════════════════════
 * الترقيم الجديد و GoBD
 * ════════════════════════════════════════════════════════════════════════
 *
 * مبدأ **Unveränderbarkeit** في GoBD يمنع التعديل **الصامت** للسجل المحاسبي.
 * وهو لا يمنع تصحيح خلل نظام إذا كان التصحيح **موثَّقاً وقابلاً للتتبع**.
 *
 * وهنا لا خيار ثالث: أرقامٌ متكررة تعني أن الفواتير **غير قابلة للتعريف**،
 * وهو بالضبط ما يشترطه §14 UStG (`einmalig vergeben` — رقم يُمنح مرة واحدة).
 * فترك التكرار ليس «حفاظاً على السجل»، بل حفاظٌ على سجلٍ مكسور.
 *
 * لذلك:
 *   • **أول** فاتورة تحمل الرقم المكرر (الأقدم بـ created_at) **تُبقي رقمها**
 *     — فالنسخة المطبوعة بيد الزبون تبقى مطابقة.
 *   • البقية تأخذ أرقاماً جديدة من نهاية المسلسل.
 *   • كل تغيير يُكتب في `invoice_data.number_correction` بالرقم القديم
 *     والجديد والسبب والتاريخ، ويُسجَّل في اللوج.
 *
 * ════════════════════════════════════════════════════════════════════════
 * ما لا تفعله هذه المايقريشن — عن قصد
 * ════════════════════════════════════════════════════════════════════════
 *
 * لا تُضيف `unique(['provider_id', 'start_time'])` على `appointments`، خلافاً
 * لما يقترحه تقرير التدقيق في DB-01. صلاحية `force_booking` **تسمح بالتداخل
 * المتعمد** (مانيكير أثناء تفاعل الصبغة) وهو قرار تصميمي موثَّق — والقيد
 * سيرفضه ويكسر ميزة قائمة. منع الحجز المزدوج غير المقصود يقع في
 * `BookingLockService` + `assertNoConflictingAppointment()`، لا في فهرس.
 */
return new class extends Migration
{
    /** ملخص ما تغيّر — يُطبع في نهاية التنفيذ. */
    private array $report = [];

    public function up(): void
    {
        DB::transaction(function () {
            $this->repairInvoiceNumbers();
            $this->repairDuplicateInvoicesPerAppointment();
            $this->repairSimpleNumberColumn('payments', 'payment_number', 'PAY');
            $this->repairSimpleNumberColumn('appointments', 'number', 'APT');
            $this->deduplicateProviderService();
            $this->reseedCounters();
            $this->addConstraints();
        });

        foreach ($this->report as $line) {
            Log::info('[MON-03 migration] ' . $line);
            if (app()->runningInConsole()) {
                echo '  ' . $line . PHP_EOL;
            }
        }
    }

    public function down(): void
    {
        // القيود فقط تُرفَع. الأرقام المُصحَّحة **لا تُعاد** إلى تكرارها:
        // إرجاعها يعني إعادة كسر السجل، ولا معنى محاسبياً لذلك.
        Schema::table('invoices', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'invoices', 'invoices_number_unique');
            $this->dropIndexIfExists($table, 'invoices', 'invoices_appointment_unique');
        });

        Schema::table('payments', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'payments', 'payments_number_unique');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'appointments', 'appointments_number_unique');
        });

        Schema::table('provider_service', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'provider_service', 'provider_service_unique');
        });
    }

    // ── 1. أرقام الفواتير ────────────────────────────────────────────────

    /**
     * أعِد ترقيم الفواتير التي تتقاسم رقماً واحداً.
     *
     * الأقدم يُبقي رقمه؛ البقية تأخذ أرقاماً جديدة تُلحَق بنهاية المسلسل.
     */
    private function repairInvoiceNumbers(): void
    {
        $duplicates = DB::table('invoices')
            ->select('invoice_number', DB::raw('COUNT(*) as c'))
            ->whereNotNull('invoice_number')
            ->groupBy('invoice_number')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('c', 'invoice_number');

        if ($duplicates->isEmpty()) {
            $this->report[] = 'invoice_number: no duplicates found.';

            return;
        }

        $this->report[] = sprintf(
            'invoice_number: %d duplicated value(s) covering %d rows.',
            $duplicates->count(),
            $duplicates->sum()
        );

        $renumbered = 0;

        foreach ($duplicates->keys() as $number) {
            // الأقدم أولاً — الأقدم يحتفظ برقمه. `id` كفاصل عند تساوي
            // created_at (البذر ينشئ صفوفاً في نفس الثانية).
            $rows = DB::table('invoices')
                ->where('invoice_number', $number)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['id', 'invoice_number', 'invoice_data', 'created_at']);

            foreach ($rows->skip(1) as $row) {
                $year      = substr((string) $row->created_at, 0, 4) ?: now()->format('Y');
                $newNumber = $this->nextFreeInvoiceNumber($year);

                $data = json_decode((string) ($row->invoice_data ?? '{}'), true) ?: [];

                // التوثيق هو ما يجعل هذا التغيير مقبولاً محاسبياً.
                $data['number_correction'] = [
                    'previous_number' => $row->invoice_number,
                    'new_number'      => $newNumber,
                    'corrected_at'    => now()->toISOString(),
                    'reason'          => 'MON-03: DocumentNumberGenerator read the newest invoice row '
                        . '(ORDER BY id DESC) instead of the highest number. Draft invoices carry a NULL '
                        . 'invoice_number, so the suffix parsed as 0 and every invoice was issued as '
                        . '<PREFIX>-<YEAR>-000001. Duplicates were renumbered to satisfy the uniqueness '
                        . 'required by §14 UStG; the earliest invoice of each duplicate group kept its number.',
                ];

                DB::table('invoices')->where('id', $row->id)->update([
                    'invoice_number' => $newNumber,
                    'invoice_data'   => json_encode($data, JSON_UNESCAPED_UNICODE),
                    'updated_at'     => now(),
                ]);

                $this->report[] = sprintf(
                    '  invoice #%d: %s -> %s',
                    $row->id,
                    $row->invoice_number,
                    $newNumber
                );
                $renumbered++;
            }
        }

        $this->report[] = sprintf('invoice_number: %d row(s) renumbered.', $renumbered);
    }

    /**
     * أول رقم فاتورة غير مستعمل في هذه السنة.
     *
     * يُفحَص وجوده فعلاً بدل الاعتماد على MAX: البيانات القديمة قد تحمل
     * صيغاً مختلطة، والفحص المباشر لا يمكن أن يُخطئ.
     */
    private function nextFreeInvoiceNumber(string $year): string
    {
        $candidate = $this->highestInvoiceSuffix($year);

        do {
            $candidate++;
            $number = sprintf('INV-%s-%06d', $year, $candidate);
        } while (DB::table('invoices')->where('invoice_number', $number)->exists());

        return $number;
    }

    private function highestInvoiceSuffix(string $year): int
    {
        $highest = 0;

        DB::table('invoices')
            ->whereNotNull('invoice_number')
            ->where('invoice_number', 'like', 'INV-' . $year . '-%')
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$highest) {
                foreach ($rows as $row) {
                    if (preg_match('/(\d+)$/', (string) $row->invoice_number, $m)) {
                        $highest = max($highest, (int) $m[1]);
                    }
                }
            });

        return $highest;
    }

    // ── 2. أكثر من فاتورة لموعد واحد ─────────────────────────────────────

    /**
     * موعدٌ يحمل أكثر من فاتورة: تُحفظ الفاتورة **المُنهاة** وتُحذف المسودات
     * المهجورة.
     *
     * كان `createInvoiceFromAppointment()` يُدرج صفاً جديداً بدل رفع المسودة،
     * فيبقى للموعد فاتورتان. و`invoice()` علاقة `HasOne` ترجع واحدة اعتباطياً.
     *
     * تُحذف **المسودات فقط**: مسودة بلا رقم لم تصدر لأحد ولا قيمة محاسبية لها.
     * ولو وُجد موعد يحمل **فاتورتين مُنهاتين** فلا تُحذف أيٌّ منهما — تُبلَّغ
     * ويُترك القرار للمحاسب، ثم تفشل الـ migration بصوت عالٍ بدل أن تحذف
     * مستنداً صادراً.
     */
    private function repairDuplicateInvoicesPerAppointment(): void
    {
        $offenders = DB::table('invoices')
            ->select('appointment_id', DB::raw('COUNT(*) as c'))
            ->whereNotNull('appointment_id')
            ->groupBy('appointment_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('c', 'appointment_id');

        if ($offenders->isEmpty()) {
            $this->report[] = 'invoices.appointment_id: no appointment carries more than one invoice.';

            return;
        }

        $deleted = 0;

        foreach ($offenders->keys() as $appointmentId) {
            $rows = DB::table('invoices')
                ->where('appointment_id', $appointmentId)
                ->orderBy('id')
                ->get(['id', 'invoice_number', 'status']);

            $finalized = $rows->where('status', '!=', InvoiceStatus::DRAFT->value);

            if ($finalized->count() > 1) {
                throw new RuntimeException(sprintf(
                    'Appointment #%s carries %d FINALIZED invoices (%s). This migration will not delete '
                    . 'an issued document. Decide with your accountant which one stands, cancel the other '
                    . '(status CANCELLED) or detach it, then re-run the migration.',
                    $appointmentId,
                    $finalized->count(),
                    $finalized->pluck('invoice_number')->implode(', ')
                ));
            }

            // احفظ المُنهاة إن وُجدت، وإلا فاحفظ الأقدم.
            $keep = $finalized->first() ?? $rows->first();

            foreach ($rows as $row) {
                if ($row->id === $keep->id) {
                    continue;
                }

                DB::table('invoice_items')->where('invoice_id', $row->id)->delete();
                DB::table('invoices')->where('id', $row->id)->delete();

                $this->report[] = sprintf(
                    '  appointment #%s: dropped abandoned DRAFT invoice #%d (kept #%d %s)',
                    $appointmentId,
                    $row->id,
                    $keep->id,
                    $keep->invoice_number ?? '(no number)'
                );
                $deleted++;
            }
        }

        $this->report[] = sprintf(
            'invoices.appointment_id: %d appointment(s) had extra invoices; %d abandoned draft(s) removed.',
            $offenders->count(),
            $deleted
        );
    }

    // ── 3. أرقام الدفع والمواعيد ─────────────────────────────────────────

    /**
     * أعِد ترقيم التكرارات في عمود رقمٍ بسيط (لا توثيق JSON فيه).
     *
     * اللاحقة الجديدة تُبنى من مسلسل السنة، وتُفحَص حتى تُوجد قيمة حرة.
     */
    private function repairSimpleNumberColumn(string $table, string $column, string $prefix): void
    {
        $duplicates = DB::table($table)
            ->select($column, DB::raw('COUNT(*) as c'))
            ->whereNotNull($column)
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1')
            ->pluck('c', $column);

        if ($duplicates->isEmpty()) {
            $this->report[] = "{$table}.{$column}: no duplicates found.";

            return;
        }

        $renumbered = 0;

        foreach ($duplicates->keys() as $value) {
            $rows = DB::table($table)
                ->where($column, $value)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['id', $column, 'created_at']);

            foreach ($rows->skip(1) as $row) {
                $year      = substr((string) $row->created_at, 0, 4) ?: now()->format('Y');
                $candidate = 0;

                do {
                    $candidate++;
                    $newValue = sprintf('%s-%s-%06d', $prefix, $year, $candidate);
                } while (DB::table($table)->where($column, $newValue)->exists());

                DB::table($table)->where('id', $row->id)->update([
                    $column      => $newValue,
                    'updated_at' => now(),
                ]);

                $this->report[] = sprintf('  %s #%d: %s -> %s', $table, $row->id, $value, $newValue);
                $renumbered++;
            }
        }

        $this->report[] = sprintf(
            '%s.%s: %d duplicated value(s), %d row(s) renumbered.',
            $table,
            $column,
            $duplicates->count(),
            $renumbered
        );
    }

    // ── 4. provider_service ──────────────────────────────────────────────

    /**
     * صفّان لنفس (مزوّد، خدمة) يعنيان **سعراً غير محدد**.
     *
     * `BookingService::getEffectivePrice()` و
     * `ServiceAvailabilityService::getProviderServicePricing()` كلاهما يستعمل
     * `->first()` على الزوج، فقد يلتقطان صفّين مختلفين — فالعميل يرى 30 يورو
     * ويُطالَب بـ 45 عند الكاشير.
     *
     * يُحفظ الصف النشط الأقدم — وهو ما كان `getEffectivePrice()` يلتقطه على
     * الأرجح، فلا يتغير السعر المعروض بهذا التنظيف.
     */
    private function deduplicateProviderService(): void
    {
        if (! Schema::hasTable('provider_service')) {
            return;
        }

        $duplicates = DB::table('provider_service')
            ->select('provider_id', 'service_id', DB::raw('COUNT(*) as c'))
            ->groupBy('provider_id', 'service_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isEmpty()) {
            $this->report[] = 'provider_service: no duplicate pairs found.';

            return;
        }

        $deleted = 0;

        foreach ($duplicates as $pair) {
            $rows = DB::table('provider_service')
                ->where('provider_id', $pair->provider_id)
                ->where('service_id', $pair->service_id)
                ->orderByDesc('is_active')   // النشط أولاً
                ->orderBy('id')              // ثم الأقدم
                ->get();

            $keep = $rows->first();

            foreach ($rows->skip(1) as $row) {
                DB::table('provider_service')->where('id', $row->id)->delete();
                $deleted++;
            }

            $this->report[] = sprintf(
                '  provider %s / service %s: kept row #%s (price %s), removed %d duplicate(s)',
                $pair->provider_id,
                $pair->service_id,
                $keep->id,
                $keep->custom_price ?? 'default',
                $rows->count() - 1
            );
        }

        $this->report[] = sprintf(
            'provider_service: %d duplicated pair(s), %d row(s) removed.',
            $duplicates->count(),
            $deleted
        );
    }

    // ── 5. إعادة بذر العدّادات ───────────────────────────────────────────

    /**
     * أعِد ضبط العدّادات على أعلى رقم موجود **بعد** الإصلاح.
     *
     * لو تُركت على قيمتها المبذورة قبل الترقيم، لأصدرت أرقاماً تصطدم بما
     * أنشأناه للتو.
     */
    private function reseedCounters(): void
    {
        if (! Schema::hasTable('document_counters')) {
            return;
        }

        $year = now()->format('Y');

        foreach ([
            'invoice' => ['invoices', 'invoice_number'],
            'payment' => ['payments', 'payment_number'],
        ] as $series => [$table, $column]) {
            $highest = 0;

            DB::table($table)
                ->whereNotNull($column)
                ->where($column, 'like', '%-' . $year . '-%')
                ->orderBy('id')
                ->chunk(500, function ($rows) use ($column, &$highest) {
                    foreach ($rows as $row) {
                        if (preg_match('/(\d+)$/', (string) $row->$column, $m)) {
                            $highest = max($highest, (int) $m[1]);
                        }
                    }
                });

            DB::table('document_counters')->where('series', $series)->update([
                'period'     => $year,
                'current'    => $highest,
                'updated_at' => now(),
            ]);

            $this->report[] = sprintf(
                'counter "%s" reseeded to %d for period %s.',
                $series,
                $highest,
                $year
            );
        }
    }

    // ── 6. القيود ────────────────────────────────────────────────────────

    private function addConstraints(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! $this->hasIndex('invoices', 'invoices_number_unique')) {
                $table->unique('invoice_number', 'invoices_number_unique');
            }
            // NULL متعددة مسموحة في MySQL وSQLite، فالمسودات (بلا رقم) لا
            // تتعارض — وهذا مقصود: المسودة ليست مستنداً صادراً.
            if (! $this->hasIndex('invoices', 'invoices_appointment_unique')) {
                $table->unique('appointment_id', 'invoices_appointment_unique');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (! $this->hasIndex('payments', 'payments_number_unique')) {
                $table->unique('payment_number', 'payments_number_unique');
            }
        });

        Schema::table('appointments', function (Blueprint $table) {
            if (! $this->hasIndex('appointments', 'appointments_number_unique')) {
                $table->unique('number', 'appointments_number_unique');
            }
        });

        if (Schema::hasTable('provider_service')) {
            Schema::table('provider_service', function (Blueprint $table) {
                if (! $this->hasIndex('provider_service', 'provider_service_unique')) {
                    $table->unique(['provider_id', 'service_id'], 'provider_service_unique');
                }
            });
        }

        $this->report[] = 'Unique constraints applied: invoices(invoice_number), '
            . 'invoices(appointment_id), payments(payment_number), appointments(number), '
            . 'provider_service(provider_id, service_id).';
    }

    private function hasIndex(string $table, string $index): bool
    {
        try {
            return collect(
                Schema::getConnection()->getSchemaBuilder()->getIndexes($table)
            )->contains(fn ($i) => ($i['name'] ?? null) === $index);
        } catch (\Throwable) {
            return false;
        }
    }

    private function dropIndexIfExists(Blueprint $table, string $tableName, string $index): void
    {
        if ($this->hasIndex($tableName, $index)) {
            $table->dropUnique($index);
        }
    }
};
