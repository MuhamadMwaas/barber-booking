<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * تُرفَع حين يُطلب حذف موعد (أو مجموعة مواعيد) تمنعه قواعد الحذف — DEL-01.
 *
 * تحمل **كل** المواعيد الممنوعة لا أولها فقط: الحذف الجماعي في Filament
 * يرفض الطلب كله إن كان فيه موعد واحد ممنوع، والموظف يحتاج أن يرى القائمة
 * كاملة ليصحح اختياره مرة واحدة بدل أن يكتشف الموانع واحداً تلو الآخر.
 *
 * كل عنصر في `$blocked`:
 *   - number           رقم الموعد (APT-...)
 *   - reason           أحد ثوابت REASON_*
 *   - children_numbers أرقام الأبناء النشطين (لسبب HAS_ACTIVE_CHILDREN فقط)
 */
class AppointmentNotDeletableException extends RuntimeException
{
    /** دُفع ثمنه، أو صدرت فاتورته (لم تعد مسودة) — سجل مالي لا يُحذف. */
    public const REASON_PAID = 'paid';

    /** الخدمة قُدّمت فعلاً. */
    public const REASON_COMPLETED = 'completed';

    /** أب ما زال له أبناء غير ملغين — حذفه يفصلهم عن فاتورتهم. */
    public const REASON_HAS_ACTIVE_CHILDREN = 'has_active_children';

    /**
     * @param  array<int, array{number: string, reason: string, children_numbers: array<int, string>}>  $blocked
     */
    public function __construct(public readonly array $blocked)
    {
        parent::__construct(sprintf(
            'Appointment deletion blocked: %s',
            implode(', ', array_map(
                fn (array $item) => "{$item['number']} ({$item['reason']})",
                $blocked
            ))
        ));
    }

    /**
     * سبب المنع بلغة المستخدم لموعد واحد — بلا رقم الموعد، لأن الواجهة
     * التي حذفت موعداً واحداً تعرف أي موعد تعرض.
     */
    public static function reasonMessage(array $item): string
    {
        return match ($item['reason']) {
            self::REASON_PAID => __('dashboard.appointment_modal.cannot_delete_paid'),
            self::REASON_COMPLETED => __('dashboard.appointment_modal.cannot_delete_completed'),
            self::REASON_HAS_ACTIVE_CHILDREN => __('dashboard.cannot_delete_has_children', [
                'numbers' => implode(', #', $item['children_numbers']),
            ]),
        };
    }

    /**
     * الرسالة المعروضة للمستخدم.
     *
     * موعد واحد → سبب المنع وحده (نفس ما كان يعرضه الداشبورد حرفياً).
     * أكثر من موعد → سطر لكل موعد مسبوقاً برقمه.
     */
    public function userMessage(): string
    {
        if (count($this->blocked) === 1) {
            return self::reasonMessage($this->blocked[0]);
        }

        return implode("\n", array_map(
            fn (array $item) => "#{$item['number']}: ".self::reasonMessage($item),
            $this->blocked
        ));
    }
}
