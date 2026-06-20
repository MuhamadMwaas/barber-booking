<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convert the hard-coded "served by" name line (static "Luay") on the seeded
     * invoice templates into a dynamic field that resolves to the appointment's
     * service provider name.
     *
     * Templates render from template_lines rows, so changing the seeder alone does
     * NOT touch invoices/templates that were already seeded — this data migration
     * fixes the existing rows. See docs/TemplateInvoice DocAgent.md.
     */
    public function up(): void
    {
        foreach (DB::table('template_lines')->where('type', 'text')->get() as $line) {
            $props = json_decode($line->properties, true) ?: [];

            if (($props['content_type'] ?? null) === 'static' && ($props['static_value'] ?? null) === 'Luay') {
                $props['content_type'] = 'dynamic';
                $props['dynamic_field'] = 'employee.name';
                unset($props['static_value']);

                DB::table('template_lines')
                    ->where('id', $line->id)
                    ->update(['properties' => json_encode($props)]);
            }
        }
    }

    public function down(): void
    {
        foreach (DB::table('template_lines')->where('type', 'text')->get() as $line) {
            $props = json_decode($line->properties, true) ?: [];

            if (($props['content_type'] ?? null) === 'dynamic' && ($props['dynamic_field'] ?? null) === 'employee.name') {
                $props['content_type'] = 'static';
                $props['static_value'] = 'Luay';
                unset($props['dynamic_field']);

                DB::table('template_lines')
                    ->where('id', $line->id)
                    ->update(['properties' => json_encode($props)]);
            }
        }
    }
};
