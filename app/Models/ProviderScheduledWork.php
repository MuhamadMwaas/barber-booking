<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ProviderScheduledWork extends Model
{
    use HasFactory;
    protected $table = 'provider_scheduled_works';


    protected $fillable = [
        'user_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_work_day',
        'break_minutes',
        'notes',
        'is_active',
    ];


    protected $casts = [
        'day_of_week' => 'integer',
        'is_work_day' => 'boolean',
        'break_minutes' => 'integer',
        'is_active' => 'boolean',

    ];

    public const DAYS = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    public const DAYS_AR = [
        0 => 'الأحد',
        1 => 'الاثنين',
        2 => 'الثلاثاء',
        3 => 'الأربعاء',
        4 => 'الخميس',
        5 => 'الجمعة',
        6 => 'السبت',
    ];
    // Relationships

    public function provider()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

        // ═══════════════════════════════════════════════════════════════
    // Accessors (الخصائص المحسوبة)
    // ═══════════════════════════════════════════════════════════════

    /**
     * الحصول على اسم اليوم بالإنجليزية
     */
    public function getDayNameAttribute(): string
    {
        return self::DAYS[$this->day_of_week] ?? 'Unknown';
    }

    /**
     * الحصول على اسم اليوم بالعربية
     */
    public function getDayNameArAttribute(): string
    {
        return self::DAYS_AR[$this->day_of_week] ?? 'غير معروف';
    }

    /**
     * الحصول على اسم اليوم حسب اللغة الحالية
     */
    public function getLocalizedDayNameAttribute(): string
    {
        $locale = app()->getLocale();
        return $locale === 'ar' ? $this->day_name_ar : $this->day_name;
    }

    /**
     * الحصول على مدة الشفت بالدقائق (بدون خصم الاستراحة)
     */
    public function getDurationMinutesAttribute(): int
    {
        return $this->calculateDurationMinutes($this->start_time, $this->end_time);
    }

    /**
     * الحصول على دقائق العمل الفعلية (بعد خصم الاستراحة)
     */
    public function getWorkingMinutesAttribute(): int
    {
        return max(0, $this->duration_minutes - $this->break_minutes);
    }

    /**
     * الحصول على وقت البداية منسق
     */
    public function getFormattedStartTimeAttribute(): string
    {
        return Carbon::parse($this->start_time)->format('h:i A');
    }

    /**
     * الحصول على وقت النهاية منسق
     */
    public function getFormattedEndTimeAttribute(): string
    {
        return Carbon::parse($this->end_time)->format('h:i A');
    }

    /**
     * الحصول على نطاق الوقت كنص
     */
    public function getTimeRangeAttribute(): string
    {
        return "{$this->formatted_start_time} - {$this->formatted_end_time}";
    }

    /**
     * الحصول على مدة الشفت منسقة
     */
    public function getFormattedDurationAttribute(): string
    {
        $hours = floor($this->duration_minutes / 60);
        $minutes = $this->duration_minutes % 60;

        if ($hours > 0 && $minutes > 0) {
            return "{$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$minutes}m";
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // Scopes (نطاقات الاستعلام)
    // ═══════════════════════════════════════════════════════════════

    /**
     * الشفتات الفعالة فقط
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * أيام العمل فقط (ليست إجازات)
     */
    public function scopeWorkDays($query)
    {
        return $query->where('is_work_day', true);
    }

    /**
     * شفتات يوم معين
     */
    public function scopeForDay($query, int $day)
    {
        return $query->where('day_of_week', $day);
    }

    /**
     * شفتات موظف معين
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * شفتات موظف في يوم معين
     */
    public function scopeForUserDay($query, int $userId, int $day)
    {
        return $query->where('user_id', $userId)->where('day_of_week', $day);
    }

    /**
     * الشفتات مرتبة حسب وقت البداية
     */
    public function scopeOrderedByTime($query)
    {
        return $query->orderBy('day_of_week')->orderBy('start_time');
    }

    // ═══════════════════════════════════════════════════════════════
    // Static Methods (الدوال الثابتة)
    // ═══════════════════════════════════════════════════════════════

    /**
     * الحصول على كل أيام الأسبوع
     */
    public static function getDays(): array
    {
        return self::DAYS;
    }

    /**
     * الحصول على أيام الأسبوع بالعربية
     */
    public static function getDaysAr(): array
    {
        return self::DAYS_AR;
    }

    /**
     * الحصول على أيام الأسبوع حسب اللغة
     */
    public static function getLocalizedDays(): array
    {
        return app()->getLocale() === 'ar' ? self::DAYS_AR : self::DAYS;
    }

    /**
     * التحقق من تعارض شفتين
     *
     * خوارزمية التعارض:
     * شفتان يتعارضان إذا: start1 < end2 AND start2 < end1
     *
     * أمثلة:
     * - [08:00-12:00] و [10:00-14:00] → متعارضان ✗
     * - [08:00-12:00] و [12:00-16:00] → غير متعارضين ✓ (متجاوران)
     * - [08:00-12:00] و [14:00-18:00] → غير متعارضين ✓
     *
     * @param string $start1 وقت بداية الشفت الأول
     * @param string $end1 وقت نهاية الشفت الأول
     * @param string $start2 وقت بداية الشفت الثاني
     * @param string $end2 وقت نهاية الشفت الثاني
     * @return bool true إذا كان هناك تعارض
     */
    public static function shiftsOverlap(
        string $start1,
        string $end1,
        string $start2,
        string $end2
    ): bool {
        // تحويل الأوقات إلى دقائق من بداية اليوم للمقارنة السهلة
        $s1 = self::timeToMinutes($start1);
        $e1 = self::timeToMinutes($end1);
        $s2 = self::timeToMinutes($start2);
        $e2 = self::timeToMinutes($end2);

        // شرط التعارض: start1 < end2 AND start2 < end1
        return $s1 < $e2 && $s2 < $e1;
    }

    /**
     * تحويل وقت إلى دقائق من بداية اليوم
     *
     * @param string $time الوقت بصيغة HH:MM أو H:MM
     * @return int عدد الدقائق من منتصف الليل
     */
    public static function timeToMinutes(string $time): int
    {
        // معالجة الصيغ المختلفة للوقت
        $time = trim($time);

        // إذا كان الوقت يحتوي على AM/PM
        if (preg_match('/(\d{1,2}):(\d{2})\s*(AM|PM)/i', $time, $matches)) {
            $hours = (int) $matches[1];
            $minutes = (int) $matches[2];
            $period = strtoupper($matches[3]);

            if ($period === 'PM' && $hours !== 12) {
                $hours += 12;
            } elseif ($period === 'AM' && $hours === 12) {
                $hours = 0;
            }

            return $hours * 60 + $minutes;
        }

        // صيغة 24 ساعة (HH:MM أو HH:MM:SS)
        $parts = explode(':', $time);
        $hours = (int) ($parts[0] ?? 0);
        $minutes = (int) ($parts[1] ?? 0);

        return $hours * 60 + $minutes;
    }

    /**
     * تحويل دقائق إلى صيغة وقت
     *
     * @param int $minutes عدد الدقائق من منتصف الليل
     * @return string الوقت بصيغة HH:MM
     */
    public static function minutesToTime(int $minutes): string
    {
        $hours = floor($minutes / 60) % 24;
        $mins = $minutes % 60;

        return sprintf('%02d:%02d', $hours, $mins);
    }

    /**
     * التحقق من وجود تعارضات في مجموعة شفتات
     *
     * @param Collection|array $shifts مجموعة الشفتات للتحقق منها
     * @return array قائمة بالتعارضات المكتشفة
     */
    public static function findOverlaps($shifts): array
    {
        $shiftsArray = $shifts instanceof Collection ? $shifts->toArray() : $shifts;
        $overlaps = [];
        $count = count($shiftsArray);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $shift1 = $shiftsArray[$i];
                $shift2 = $shiftsArray[$j];

                // تحقق فقط إذا كانا في نفس اليوم
                $day1 = $shift1['day_of_week'] ?? $shift1->day_of_week ?? null;
                $day2 = $shift2['day_of_week'] ?? $shift2->day_of_week ?? null;

                if ($day1 !== $day2) {
                    continue;
                }

                $start1 = $shift1['start_time'] ?? $shift1->start_time ?? '';
                $end1 = $shift1['end_time'] ?? $shift1->end_time ?? '';
                $start2 = $shift2['start_time'] ?? $shift2->start_time ?? '';
                $end2 = $shift2['end_time'] ?? $shift2->end_time ?? '';

                if (self::shiftsOverlap($start1, $end1, $start2, $end2)) {
                    $overlaps[] = [
                        'day' => $day1,
                        'day_name' => self::DAYS[$day1] ?? 'Unknown',
                        'shift1' => [
                            'start' => $start1,
                            'end' => $end1,
                            'index' => $i,
                        ],
                        'shift2' => [
                            'start' => $start2,
                            'end' => $end2,
                            'index' => $j,
                        ],
                    ];
                }
            }
        }

        return $overlaps;
    }

    /**
     * إنشاء جدول أسبوعي فارغ
     *
     * @return array مصفوفة بأيام الأسبوع كمفاتيح وقوائم فارغة كقيم
     */
    public static function createEmptyWeek(): array
    {
        $week = [];
        foreach (self::DAYS as $dayNum => $dayName) {
            $week[$dayNum] = [];
        }
        return $week;
    }

    /**
     * جلب شفتات موظف كجدول أسبوعي
     *
     * @param int $userId معرف الموظف
     * @return array شفتات الموظف مجمعة حسب اليوم
     */
    public static function getWeeklySchedule(int $userId): array
    {
        $shifts = self::forUser($userId)
            ->active()
            ->orderedByTime()
            ->get();

        $week = self::createEmptyWeek();

        foreach ($shifts as $shift) {
            $week[$shift->day_of_week][] = [
                'id' => $shift->id,
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time,
                'is_work_day' => $shift->is_work_day,
                'break_minutes' => $shift->break_minutes,
                'notes' => $shift->notes,
            ];
        }

        return $week;
    }

    // ═══════════════════════════════════════════════════════════════
    // Instance Methods (دوال الكائن)
    // ═══════════════════════════════════════════════════════════════

    /**
     * حساب مدة الشفت بالدقائق
     */
    public function calculateDurationMinutes(string $start, string $end): int
    {
        $startMinutes = self::timeToMinutes($start);
        $endMinutes = self::timeToMinutes($end);

        // معالجة حالة الشفت الذي يمتد لليوم التالي
        if ($endMinutes <= $startMinutes) {
            $endMinutes += 24 * 60; // إضافة 24 ساعة
        }

        return $endMinutes - $startMinutes;
    }

    /**
     * التحقق من صحة الوقت (البداية قبل النهاية)
     */
    public function isValidTimeRange(): bool
    {
        $start = self::timeToMinutes($this->start_time);
        $end = self::timeToMinutes($this->end_time);

        // السماح بالشفتات التي تمتد لليوم التالي
        return $start !== $end;
    }

    /**
     * التحقق من تعارض هذا الشفت مع شفت آخر
     */
    public function overlapsWith(self $other): bool
    {
        // لا تعارض إذا كانا في أيام مختلفة
        if ($this->day_of_week !== $other->day_of_week) {
            return false;
        }

        return self::shiftsOverlap(
            $this->start_time,
            $this->end_time,
            $other->start_time,
            $other->end_time
        );
    }

    /**
     * نسخ الشفت (إنشاء نسخة جديدة)
     */
    public function duplicate(?int $newUserId = null, ?int $newDayOfWeek = null): self
    {
        $clone = $this->replicate();

        if ($newUserId !== null) {
            $clone->user_id = $newUserId;
        }

        if ($newDayOfWeek !== null) {
            $clone->day_of_week = $newDayOfWeek;
        }

        return $clone;
    }
}
