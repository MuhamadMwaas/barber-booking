<?php

use App\Models\InvoiceTemplate;
use App\Models\TemplateLine;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Retrofits every existing invoice template with two new body lines so the
 * discount is visible on the printed receipt:
 *
 *     Artikel gesamt / Items total   →  invoice.items_total   (pre-discount gross)
 *     Rabatt        / Discount        →  invoice.discount       (-amount)
 *
 * Both are `two_column` lines with hide_when_empty = true, so they ONLY appear
 * when a discount exists; full-price invoices look exactly as before.
 *
 * They are inserted immediately BEFORE the existing "Net/Netto" line
 * (dynamic_field = invoice.subtotal). Templates live in the DB, so editing the
 * seeder alone is not enough for already-seeded rows — hence this data migration.
 *
 * Idempotent: skips any template that already has an invoice.discount line.
 */
return new class extends Migration
{
    /** Localised labels per template language. */
    private array $labels = [
        'de'      => ['items' => 'Artikel gesamt', 'discount' => 'Rabatt'],
        'en'      => ['items' => 'Items total',    'discount' => 'Discount'],
        'ar'      => ['items' => 'إجمالي الأصناف',  'discount' => 'خصم'],
        'default' => ['items' => 'Items total',    'discount' => 'Discount'],
    ];

    public function up(): void
    {
        InvoiceTemplate::query()->with('lines')->get()->each(function (InvoiceTemplate $template) {
            $bodyLines = $template->lines->where('section', 'body');

            // Already migrated?
            $alreadyHasDiscount = $bodyLines->contains(
                fn (TemplateLine $line) => ($line->properties['dynamic_field'] ?? null) === 'invoice.discount'
            );
            if ($alreadyHasDiscount) {
                return;
            }

            // Anchor = the existing Net/Netto line.
            $subtotalLine = $bodyLines->first(
                fn (TemplateLine $line) => ($line->properties['dynamic_field'] ?? null) === 'invoice.subtotal'
            );
            if (! $subtotalLine) {
                return; // Template does not use the two_column totals pattern → nothing to do.
            }

            $anchorOrder = (int) $subtotalLine->order;
            $labels = $this->labels[$template->language] ?? $this->labels['default'];

            // Open two slots at the anchor position.
            DB::table('template_lines')
                ->where('template_id', $template->id)
                ->where('section', 'body')
                ->where('order', '>=', $anchorOrder)
                ->increment('order', 2);

            // Inherit styling from the Net line so the new rows match visually.
            $base = $subtotalLine->properties ?? [];
            $labelWidth = $base['label_width'] ?? 60;
            $fontSize   = $base['font_size'] ?? 9;
            $alignment  = $base['alignment'] ?? 'left';

            $template->lines()->create([
                'section'    => 'body',
                'type'       => 'two_column',
                'order'      => $anchorOrder,
                'is_enabled' => true,
                'properties' => [
                    'label'          => $labels['items'],
                    'label_width'    => $labelWidth,
                    'value_type'     => 'dynamic',
                    'dynamic_field'  => 'invoice.items_total',
                    'font_size'      => $fontSize,
                    'label_bold'     => false,
                    'alignment'      => $alignment,
                    'margin_bottom'  => 1,
                    'hide_when_empty' => true,
                ],
            ]);

            $template->lines()->create([
                'section'    => 'body',
                'type'       => 'two_column',
                'order'      => $anchorOrder + 1,
                'is_enabled' => true,
                'properties' => [
                    'label'          => $labels['discount'],
                    'label_width'    => $labelWidth,
                    'value_type'     => 'dynamic',
                    'dynamic_field'  => 'invoice.discount',
                    'font_size'      => $fontSize,
                    'label_bold'     => false,
                    'alignment'      => $alignment,
                    'margin_bottom'  => 2,
                    'hide_when_empty' => true,
                ],
            ]);
        });
    }

    public function down(): void
    {
        // Delete via the model so TemplateLine::deleted reorders the rest.
        TemplateLine::query()
            ->where('section', 'body')
            ->get()
            ->filter(fn (TemplateLine $line) => in_array(
                $line->properties['dynamic_field'] ?? null,
                ['invoice.items_total', 'invoice.discount'],
                true
            ))
            ->each(fn (TemplateLine $line) => $line->delete());
    }
};
