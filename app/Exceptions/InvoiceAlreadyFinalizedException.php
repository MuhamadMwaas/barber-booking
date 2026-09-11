<?php

namespace App\Exceptions;

use App\Models\Invoice;
use RuntimeException;

/**
 * تُرفَع حين يُطلب إنهاء فاتورة أُنهيت فعلاً — MON-03.
 *
 * لماذا استثناء خاص وليس `InvalidArgumentException` عامّاً؟ لأن هذه ليست
 * حالة خطأ من الموظف: إنها **الطلب الثاني من ضغطة مزدوجة** أو إعادة محاولة
 * بعد انقطاع شبكة وصل فيها الطلب الأول فعلاً. النقود قُبضت والفاتورة صدرت،
 * والشيء الصحيح أن يُقال للموظف «هذه الفاتورة مُنهاة، رقمها كذا» ويُعاد
 * طبعها — لا أن تُعرض له رسالة فشل مُفزعة عن معاملة نجحت.
 *
 * فهي تحمل الفاتورة المُنهاة معها ليتمكن المستدعي من فعل ذلك.
 */
class InvoiceAlreadyFinalizedException extends RuntimeException
{
    public function __construct(
        public readonly Invoice $invoice,
        ?string $message = null,
    ) {
        parent::__construct(
            $message ?? sprintf(
                'Invoice %s is already finalized (status: %s).',
                $invoice->invoice_number ?? "#{$invoice->id}",
                $invoice->status->getLabel(),
            )
        );
    }
}
