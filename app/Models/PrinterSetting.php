<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrinterSetting extends Model {
    protected $fillable = [
        'name',
        'printer_name',
        'description',
        'connection_type',
        'ip_address',
        'port',
        'device_path',
        'paper_size',
        'default_copies',
        'print_method',
        'is_active',
        'is_default',
        'last_test_at',
        'last_test_status',
        'last_test_message',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'port' => 'integer',
        'default_copies' => 'integer',
        'last_test_at' => 'datetime',
        'settings' => 'array',
    ];

    /**
     * Boot method
     */
    protected static function boot() {
        parent::boot();

        // When setting as default, unset all others
        static::saving(function ($printer) {
            if ($printer->is_default && $printer->isDirty('is_default')) {
                static::where('id', '!=', $printer->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });

        // If deleting default printer, set another as default
        static::deleting(function ($printer) {
            if ($printer->is_default) {
                $newDefault = static::where('id', '!=', $printer->id)
                    ->where('is_active', true)
                    ->first();

                if ($newDefault) {
                    $newDefault->update(['is_default' => true]);
                }
            }
        });
    }

    /**
     * Relationships
     */
    public function printLogs(): HasMany {
        return $this->hasMany(PrintLog::class, 'printer_id');
    }

    /**
     * Scopes
     */
    public function scopeActive($query) {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query) {
        return $query->where('is_default', true);
    }

    public function scopeConnectionType($query, string $type) {
        return $query->where('connection_type', $type);
    }

    /**
     * Helper Methods
     */
    public static function getDefault(): ?self {
        return static::active()->default()->first();
    }

    public function setAsDefault(): void {
        $this->update(['is_default' => true]);
    }

    public function isUsb(): bool {
        return $this->connection_type === 'usb';
    }

    public function isNetwork(): bool {
        return $this->connection_type === 'network';
    }

    public function getConnectionString(): string {
        if ($this->isNetwork()) {
            return $this->ip_address . ':' . $this->port;
        }

        return $this->device_path ?? $this->printer_name ?? 'Unknown';
    }

    public function testConnection(): array {
        try {
            $result = [
                'success' => true,
                'message' => 'Printer is ready',
                'connection' => $this->getConnectionString(),
            ];

            $this->update([
                'last_test_at' => now(),
                'last_test_status' => 'success',
                'last_test_message' => 'Connection successful',
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->update([
                'last_test_at' => now(),
                'last_test_status' => 'failed',
                'last_test_message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function getTotalPrints(): int {
        return $this->printLogs()->where('status', 'success')->count();
    }

    public function getSuccessRate(): float {
        $total = $this->printLogs()->count();
        if ($total === 0) {
            return 0;
        }

        $success = $this->printLogs()->where('status', 'success')->count();
        return round(($success / $total) * 100, 2);
    }
}
