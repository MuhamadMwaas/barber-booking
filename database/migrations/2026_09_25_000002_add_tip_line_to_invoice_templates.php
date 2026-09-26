<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Print the tip on receipts that are already seeded.
 *
 * Receipts render from template_lines rows, not from InvoiceTemplateSeeder, so
 * editing the seeder alone never reaches an installed salon. For every template
 * whose "Paid" line is bound to payment.amount this:
 *   1) inserts a "Tip" two_column line (invoice.tip, hide_when_empty) just
 *      before it — invisible on invoices without a tip;
 *   2) rebinds the "Paid" line to invoice.paid_amount (= total + tip), since
 *      payment.amount is the invoice total and would hide the tip the customer
 *      actually handed over.
 *
 * Idempotent: templates that already carry an invoice.tip line are skipped.
 */
return new class extends Migration
{
    private const TIP_LABELS = [
        'de' => 'Trinkgeld',
        'en' => 'Tip',
        'ar' => 'بقشيش',
    ];

    public function up(): void
    {
        $templates = DB::table('invoice_templates')->get(['id', 'language']);

        foreach ($templates as $template) {
            $lines = DB::table('template_lines')->where('template_id', $template->id)->get();

            $alreadyHasTip = $lines->contains(
                fn ($line) => (json_decode($line->properties, true)['dynamic_field'] ?? null) === 'invoice.tip'
            );
            if ($alreadyHasTip) {
                continue;
            }

            $paidLine = $lines->first(fn ($line) => $line->type === 'two_column'
                && (json_decode($line->properties, true)['dynamic_field'] ?? null) === 'payment.amount');
            if (! $paidLine) {
                continue;
            }

            DB::transaction(function () use ($template, $paidLine) {
                DB::table('template_lines')
                    ->where('template_id', $template->id)
                    ->where('section', $paidLine->section)
                    ->where('order', '>=', $paidLine->order)
                    ->increment('order');

                $paidProperties = json_decode($paidLine->properties, true);

                DB::table('template_lines')->insert([
                    'template_id' => $template->id,
                    'section' => $paidLine->section,
                    'type' => 'two_column',
                    'order' => $paidLine->order,
                    'is_enabled' => true,
                    'properties' => json_encode([
                        'label' => self::TIP_LABELS[$template->language] ?? self::TIP_LABELS['en'],
                        'label_width' => $paidProperties['label_width'] ?? 60,
                        'value_type' => 'dynamic',
                        'dynamic_field' => 'invoice.tip',
                        'font_size' => $paidProperties['font_size'] ?? 9,
                        'label_bold' => false,
                        'alignment' => $paidProperties['alignment'] ?? 'left',
                        'margin_bottom' => 1,
                        'hide_when_empty' => true,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $paidProperties['dynamic_field'] = 'invoice.paid_amount';
                DB::table('template_lines')->where('id', $paidLine->id)->update([
                    'properties' => json_encode($paidProperties),
                    'updated_at' => now(),
                ]);
            });
        }
    }

    public function down(): void
    {
        $tipLines = DB::table('template_lines')->where('type', 'two_column')->get()
            ->filter(fn ($line) => (json_decode($line->properties, true)['dynamic_field'] ?? null) === 'invoice.tip');

        foreach ($tipLines as $tipLine) {
            DB::transaction(function () use ($tipLine) {
                DB::table('template_lines')->where('id', $tipLine->id)->delete();

                DB::table('template_lines')
                    ->where('template_id', $tipLine->template_id)
                    ->where('section', $tipLine->section)
                    ->where('order', '>', $tipLine->order)
                    ->decrement('order');

                $paidLines = DB::table('template_lines')
                    ->where('template_id', $tipLine->template_id)
                    ->where('type', 'two_column')
                    ->get();

                foreach ($paidLines as $line) {
                    $properties = json_decode($line->properties, true);
                    if (($properties['dynamic_field'] ?? null) === 'invoice.paid_amount') {
                        $properties['dynamic_field'] = 'payment.amount';
                        DB::table('template_lines')->where('id', $line->id)->update([
                            'properties' => json_encode($properties),
                        ]);
                    }
                }
            });
        }
    }
};
