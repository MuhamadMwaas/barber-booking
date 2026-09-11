<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * جدول عدّادات المستندات — MON-03.
 *
 * ترقيم المستندات المالية **مشكلة تزامن، لا مشكلة تنسيق نصّي**. النمط القديم
 * `SELECT MAX(number) + 1` (أو ما هو أسوأ: `ORDER BY id DESC`) مكسور بنيوياً:
 * قراءتان متزامنتان تريان نفس القيمة، والصف الذي يُقرأ قد لا يحمل رقماً أصلاً.
 *
 * الحل: **صفٌّ عدّادٌ واحد لكل مسلسل**، يُقفل بـ `lockForUpdate()` داخل معاملة
 * المستدعي. الصف موجود دائماً — لا يُنشأ في المسار الساخن — لأن
 * `lockForUpdate()` لا يستطيع قفل صف غير موجود (نفس درس `BOOK-02`: لا يمكن
 * قفل غياب).
 *
 * ولا نعتمد على `AUTO_INCREMENT`: فهو يترك **فجوات** عند الارتداد، لأن قيمته
 * لا ترتد مع المعاملة.
 *
 * `period` يحمل السنة التي يعدّها الصف حالياً. تصفير السنة يحدث **داخل القفل**
 * عند أول مستند في السنة الجديدة، فلا يمكن أن يتصفّر مرتين.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_counters', function (Blueprint $table) {
            // اسم المسلسل: 'invoice' أو 'payment'. مفتاح أساسي نصّي — صف واحد
            // فقط لكل مسلسل، فلا حاجة لـ id.
            $table->string('series', 32)->primary();

            // السنة التي يعدّها هذا الصف حالياً، مثل '2026'.
            $table->string('period', 16);

            // آخر رقم مُستهلك في هذه الفترة.
            $table->unsignedBigInteger('current')->default(0);

            $table->timestamps();
        });

        // بذر الصفوف الآن، لا في المسار الساخن.
        //
        // القيمة الابتدائية تُشتق من أعلى رقم **موجود فعلاً** في البيانات، لا
        // من صفر: لو بدأنا من 1 لأصدرنا أرقاماً تصطدم بفواتير مطبوعة مسبقاً.
        $year = now()->format('Y');

        DB::table('document_counters')->insert([
            [
                'series'     => 'invoice',
                'period'     => $year,
                'current'    => $this->highestSuffix('invoices', 'invoice_number', $year),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'series'     => 'payment',
                'period'     => $year,
                'current'    => $this->highestSuffix('payments', 'payment_number', $year),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_counters');
    }

    /**
     * أعلى لاحقة رقمية مستعملة في هذا الجدول لهذه السنة.
     *
     * يُفحص كل صف في PHP لا في SQL: الأرقام القديمة تحمل صيغتين مختلفتين
     * (`INV-2026-000007` و`PAY-20260910-A1B2C3`)، واستخراج اللاحقة الرقمية
     * منها بـ SQL محمول بين MySQL وSQLite غير عملي. عدد الصفوف صغير ويُنفَّذ
     * مرة واحدة عند الترقية.
     */
    private function highestSuffix(string $table, string $column, string $year): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $highest = 0;

        DB::table($table)
            ->whereNotNull($column)
            ->select($column, 'created_at')
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($column, $year, &$highest) {
                foreach ($rows as $row) {
                    $value = (string) $row->$column;

                    // نُحصي أرقام هذه السنة فقط — سواء كانت السنة في الرقم
                    // نفسه أو مأخوذة من created_at للصيغ التي لا تحملها.
                    $rowYear = str_contains($value, '-' . $year . '-')
                        ? $year
                        : substr((string) $row->created_at, 0, 4);

                    if ($rowYear !== $year) {
                        continue;
                    }

                    if (preg_match('/(\d+)$/', $value, $m)) {
                        $highest = max($highest, (int) $m[1]);
                    }
                }
            });

        return $highest;
    }
};
