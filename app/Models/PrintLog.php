<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintLog extends Model {
    protected $fillable = [
        'invoice_id',
        'template_id',
        'printer_id',
        'user_id',
        'print_number',
        'copies',
        'print_type',
        'status',
        'error_message',
        'started_at',
        'completed_at',
        'duration_ms',
        'print_data',
    ];

    protected $casts = [
        'print_number' => 'integer',
        'copies' => 'integer',
        'duration_ms' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'print_data' => 'array',
    ];

    /**
     * Relationships
     */
    public function invoice(): BelongsTo {
        return $this->belongsTo(Invoice::class);
    }

    public function template(): BelongsTo {
        return $this->belongsTo(InvoiceTemplate::class, 'template_id');
    }

    public function printer(): BelongsTo {
        return $this->belongsTo(PrinterSetting::class, 'printer_id');
    }

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /**
     * Scopes
     */
    public function scopeSuccess($query) {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query) {
        return $query->where('status', 'failed');
    }

    public function scopeForInvoice($query, int $invoiceId) {
        return $query->where('invoice_id', $invoiceId);
    }

    public function scopeForPrinter($query, int $printerId) {
        return $query->where('printer_id', $printerId);
    }

    public function scopeOriginal($query) {
        return $query->where('print_type', 'original');
    }

    public function scopeCopy($query) {
        return $query->where('print_type', 'copy');
    }

    public function scopeToday($query) {
        return $query->whereDate('created_at', today());
    }

    /**
     * Helper Methods
     */
    public function markAsStarted(): void {
        $this->update([
            'status' => 'printing',
            'started_at' => now(),
        ]);
    }

    public function markAsSuccess(): void {
        $startTime = $this->started_at ?? $this->created_at;
        $duration = now()->diffInMilliseconds($startTime);

        $this->update([
            'status' => 'success',
            'completed_at' => now(),
            'duration_ms' => $duration,
        ]);
    }

    public function markAsFailed(string $error): void {
        $startTime = $this->started_at ?? $this->created_at;
        $duration = now()->diffInMilliseconds($startTime);

        $this->update([
            'status' => 'failed',
            'error_message' => $error,
            'completed_at' => now(),
            'duration_ms' => $duration,
        ]);
    }

    public function isSuccess(): bool {
        return $this->status === 'success';
    }

    public function isFailed(): bool {
        return $this->status === 'failed';
    }

    public function isPending(): bool {
        return $this->status === 'pending';
    }

    public function isPrinting(): bool {
        return $this->status === 'printing';
    }

    public function getDurationInSeconds(): ?float {
        if (!$this->duration_ms) {
            return null;
        }

        return round($this->duration_ms / 1000, 2);
    }

    public function getCopyLabel(): string {
        if ($this->print_number === 1) {
            return '';
        }

        if ($this->print_number === 2) {
            return '(COPY)';
        }

        return '(COPY ' . ($this->print_number - 1) . ')';
    }
}
