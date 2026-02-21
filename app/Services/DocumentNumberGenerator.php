<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DocumentNumberGenerator
{
    public static function generate(
        string $table,
        string $column,
        string $prefix
    ): string {
        return DB::transaction(function () use ($table, $column, $prefix) {

            $year  = now()->format('Y');

            $lastRecord = DB::table($table)
                ->whereYear('created_at', $year)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $lastNumber = 0;

            if ($lastRecord) {
                preg_match('/(\d+)$/', $lastRecord->$column, $matches);
                $lastNumber = (int) ($matches[1] ?? 0);
            }

            $nextNumber = $lastNumber + 1;

            return sprintf(
                '%s-%s-%06d',
                $prefix,
                $year,
                $nextNumber
            );
        });
    }
}
