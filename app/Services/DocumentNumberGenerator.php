<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * مولِّد أرقام المستندات المالية — المصدر الوحيد لأي رقم متسلسل في المشروع.
 *
 * ═══════════════════════════════════════════════════════════════════════
 * ⚠️ يجب أن يُنادى **داخل معاملة المستدعي**، لا في معاملة خاصة به.
 * ═══════════════════════════════════════════════════════════════════════
 *
 * هذا هو جوهر `MON-03`. النسخة السابقة كانت:
 *
 *     return DB::transaction(function () {                 // ← معاملة خاصة
 *         $last = DB::table($table)->orderByDesc('id')->lockForUpdate()->first();
 *         preg_match('/(\d+)$/', $last->$column, $m);
 *         return sprintf('%s-%s-%06d', $prefix, $year, ((int)($m[1] ?? 0)) + 1);
 *     });                                                  // ← COMMIT: القفل يُحرَّر
 *     // المستدعي يكتب الرقم لاحقاً، في معاملة أخرى.
 *
 * وفيها ثلاثة أعطال مستقلة:
 *
 * 1. **`orderByDesc('id')` يجلب أحدث صف، لا أعلى رقم.** وكل حجز يُنشئ فاتورة
 *    مسودة بـ `invoice_number = NULL`، فأحدث صف في `invoices` مسودةٌ رقمها
 *    NULL. `preg_match` على NULL لا يجد شيئاً ⟶ `lastNumber = 0` ⟶ الرقم
 *    الناتج `INV-YYYY-000001` **دائماً**. لم يكن هذا حالة تسابق: كل فاتورة
 *    في النظام كانت تحمل الرقم نفسه، حتمياً، بلا أي تزامن.
 *
 * 2. **القفل لا يحجز شيئاً.** `DB::transaction()` الداخلية تُثبِّت وتُحرِّر
 *    القفل **قبل** أن يستهلك المستدعي الرقم — نمط «اقرأ، حرِّر، ثم اكتب».
 *    وحين لا يوجد صف للسنة، `lockForUpdate()` لا تقفل شيئاً على الإطلاق
 *    (لا يمكن قفل صفوف غير موجودة — نفس درس `BOOK-02`).
 *
 * 3. **الاعتماد على المسح.** قراءة الجدول لاستنتاج الرقم التالي تجعل كل صف
 *    مشوّه أو NULL قادراً على إعادة المسلسل إلى الصفر.
 *
 * الحل: صفّ عدّادٍ واحد في `document_counters`، يُقفل ويُحدَّث ويُستهلك كله
 * داخل **معاملة واحدة** — معاملة المستدعي. فإن ارتدّت، ارتدّ العدّاد معها
 * ولم تنشأ فجوة.
 */
class DocumentNumberGenerator
{
    /**
     * احجز الرقم التالي في المسلسل واستهلكه.
     *
     * @param  string $series  اسم المسلسل: 'invoice' أو 'payment'
     * @param  string $prefix  البادئة المطبوعة: 'INV' أو 'PAY'
     * @param  int    $padding عدد خانات اللاحقة
     * @return string مثال: 'INV-2026-000042'
     *
     * @throws RuntimeException إذا نُودي خارج معاملة، أو كان صف العدّاد مفقوداً
     */
    public static function next(string $series, string $prefix, int $padding = 6): string
    {
        // الحرس الذي يجعل العطل مستحيلاً لا مجرد غير محتمل.
        //
        // بلا معاملة محيطة، القفل أدناه يُحرَّر لحظة انتهاء هذه الدالة، فيعود
        // الرقم غير محجوز — وهو بالضبط العطل الذي أُصلح. نرفض بصوت عالٍ بدل
        // أن نُصدر رقماً لا يحميه شيء.
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException(
                "DocumentNumberGenerator::next('{$series}') must be called inside a "
                . 'transaction so the number is reserved and consumed atomically. '
                . 'Wrap the caller in DB::transaction().'
            );
        }

        $year = now()->format('Y');

        // القفل الصفّي: كل من يريد رقماً من هذا المسلسل يقف في صف واحد.
        // الصف موجود دائماً (تبذره الـ migration)، فلا يوجد فرع «غير موجود»
        // يستطيع أن يفلت بلا قفل.
        $counter = DB::table('document_counters')
            ->where('series', $series)
            ->lockForUpdate()
            ->first();

        if (! $counter) {
            throw new RuntimeException(
                "Missing document counter row for series '{$series}'. "
                . 'Run the migrations — the row is seeded there, never created on the hot path, '
                . 'because lockForUpdate() cannot lock a row that does not exist.'
            );
        }

        // تصفير السنة داخل القفل: أول مستند في السنة الجديدة يُصفّر العدّاد،
        // ولا يمكن أن يُصفّره اثنان لأن كليهما يتسلسل على نفس الصف.
        $current = $counter->period === $year ? (int) $counter->current : 0;
        $next    = $current + 1;

        DB::table('document_counters')
            ->where('series', $series)
            ->update([
                'period'     => $year,
                'current'    => $next,
                'updated_at' => now(),
            ]);

        return sprintf(
            '%s-%s-%0' . $padding . 'd',
            $prefix,
            $year,
            $next
        );
    }

    /**
     * التوقيع القديم — محفوظ لئلا ينكسر مستدعٍ لم يُحدَّث.
     *
     * `$table` و`$column` لم يبق لهما معنى: الرقم لم يعد يُستنتج بمسح الجدول،
     * بل يأتي من صف عدّاد. تُترجَم أسماء الجداول إلى أسماء المسلسلات.
     *
     * @deprecated استخدم next() مباشرةً بأسماء المسلسلات.
     */
    public static function generate(string $table, string $column, string $prefix): string
    {
        $series = match ($table) {
            'invoices' => 'invoice',
            'payments' => 'payment',
            default    => $table,
        };

        Log::debug('DocumentNumberGenerator::generate() is deprecated; call next() instead.', [
            'table'  => $table,
            'series' => $series,
        ]);

        return self::next($series, $prefix);
    }

    /**
     * آخر رقم مُستهلك في مسلسل — للتقارير والتشخيص. لا يستهلك ولا يقفل.
     */
    public static function peek(string $series): array
    {
        $counter = DB::table('document_counters')->where('series', $series)->first();

        return [
            'series'  => $series,
            'period'  => $counter->period ?? null,
            'current' => (int) ($counter->current ?? 0),
        ];
    }
}
