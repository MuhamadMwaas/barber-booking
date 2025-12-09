<?php

namespace App\Livewire;

use App\Models\ProviderScheduledWork;
use App\Models\SalonSchedule;
use App\Models\User;
use Livewire\Component;

/**
 * WeeklyScheduleTimeline - عرض وتحرير جدول الدوام الأسبوعي بشكل Timeline
 *
 * هذا المكون يوفر:
 * - عرض Timeline احترافي لكل يوم من أيام الأسبوع
 * - عرض الشفتات بشكل مرئي على Timeline
 * - تمييز أيام العطلة بلون مختلف
 * - دعم Filament Theme
 */
class WeeklyScheduleTimeline extends Component
{
    // ═══════════════════════════════════════════════════════════════
    // الخصائص (Properties)
    // ═══════════════════════════════════════════════════════════════

    /**
     * معرف الموظف
     */
    public ?int $userId = null;

    /**
     * الجدول الأسبوعي للشفتات
     * Structure: [day_of_week => [...shifts]]
     */
    public array $weeklySchedule = [];

    /**
     * جدول دوام الفرع
     */
    public array $branchSchedule = [];

    /**
     * وضع القراءة فقط (للعرض فقط بدون تعديل)
     */
    public bool $readOnly = false;

    /**
     * إظهار معلومات دوام الفرع
     */
    public bool $showBranchSchedule = true;

    // ═══════════════════════════════════════════════════════════════
    // Lifecycle Hooks
    // ═══════════════════════════════════════════════════════════════

    public function mount(): void
    {
        $this->initializeWeeklySchedule();
        $this->loadBranchSchedule();

        if ($this->userId) {
            $this->loadUserSchedule();
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // دوال التحميل
    // ═══════════════════════════════════════════════════════════════

    /**
     * تهيئة الجدول الأسبوعي الفارغ
     */
    protected function initializeWeeklySchedule(): void
    {
        for ($day = 0; $day < 7; $day++) {
            $this->weeklySchedule[$day] = [
                'day_number' => $day,
                'day_name' => $this->getDayName($day),
                'day_name_ar' => $this->getDayNameAr($day),
                'day_name_short' => $this->getDayNameShort($day),
                'is_work_day' => false,
                'shifts' => [],
                'total_hours' => 0,
                'effective_hours' => 0,
            ];
        }
    }

    /**
     * تحميل جدول دوام الفرع
     */
    protected function loadBranchSchedule(): void
    {
        $branchSchedules = SalonSchedule::query()
            ->where('is_open', true)
            ->get();

        foreach ($branchSchedules as $schedule) {
            $this->branchSchedule[$schedule->day_of_week] = [
                'is_open' => true,
                'open_time' => substr($schedule->open_time, 0, 5),
                'close_time' => substr($schedule->close_time, 0, 5),
            ];
        }
    }

    /**
     * تحميل جدول الموظف من قاعدة البيانات
     */
    protected function loadUserSchedule(): void
    {
        if (!$this->userId) {
            return;
        }

        $shifts = ProviderScheduledWork::where('user_id', $this->userId)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        foreach ($shifts as $shift) {
            $day = $shift->day_of_week;

            // حساب الساعات
            $start = \Carbon\Carbon::parse($shift->start_time);
            $end = \Carbon\Carbon::parse($shift->end_time);
            $totalMinutes = $start->diffInMinutes($end);
            $effectiveMinutes = $totalMinutes - ($shift->break_minutes ?? 0);

            $shiftData = [
                'id' => $shift->id,
                'start_time' => substr($shift->start_time, 0, 5),
                'end_time' => substr($shift->end_time, 0, 5),
                'break_minutes' => $shift->break_minutes ?? 0,
                'is_work_day' => $shift->is_work_day,
                'is_active' => $shift->is_active,
                'notes' => $shift->notes,
                'total_minutes' => $totalMinutes,
                'effective_minutes' => $effectiveMinutes,
                'start_percentage' => $this->timeToPercentage($shift->start_time),
                'duration_percentage' => ($totalMinutes / (24 * 60)) * 100,
            ];

            $this->weeklySchedule[$day]['shifts'][] = $shiftData;
            $this->weeklySchedule[$day]['is_work_day'] = true;
            $this->weeklySchedule[$day]['total_hours'] += $totalMinutes;
            $this->weeklySchedule[$day]['effective_hours'] += $effectiveMinutes;
        }

        // تحويل الدقائق إلى ساعات
        foreach ($this->weeklySchedule as $day => $data) {
            $this->weeklySchedule[$day]['total_hours'] = round($data['total_hours'] / 60, 1);
            $this->weeklySchedule[$day]['effective_hours'] = round($data['effective_hours'] / 60, 1);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // دوال مساعدة
    // ═══════════════════════════════════════════════════════════════

    /**
     * تحويل الوقت إلى نسبة مئوية من اليوم
     */
    protected function timeToPercentage(string $time): float
    {
        $minutes = ProviderScheduledWork::timeToMinutes($time);
        return ($minutes / (24 * 60)) * 100;
    }

    /**
     * الحصول على اسم اليوم بالإنجليزية
     */
    protected function getDayName(int $day): string
    {
        return ProviderScheduledWork::DAYS[$day] ?? 'Unknown';
    }

    /**
     * الحصول على اسم اليوم بالعربية
     */
    protected function getDayNameAr(int $day): string
    {
        return ProviderScheduledWork::DAYS_AR[$day] ?? 'غير معروف';
    }

    /**
     * الحصول على اسم اليوم المختصر
     */
    protected function getDayNameShort(int $day): string
    {
        $short = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        return $short[$day] ?? '';
    }

    /**
     * الحصول على اسم اليوم حسب اللغة
     */
    public function getLocalizedDayName(int $day): string
    {
        $locale = app()->getLocale();
        return $locale === 'ar' ? $this->getDayNameAr($day) : $this->getDayName($day);
    }

    /**
     * التحقق من وجود شفتات في يوم
     */
    public function hasShifts(int $day): bool
    {
        return !empty($this->weeklySchedule[$day]['shifts']);
    }

    /**
     * الحصول على لون اليوم
     */
    public function getDayColor(int $day): string
    {
        if (!$this->weeklySchedule[$day]['is_work_day']) {
            return 'gray'; // يوم عطلة
        }

        return match ($day) {
            5 => 'success', // الجمعة
            6 => 'warning', // السبت
            default => 'primary', // أيام العمل العادية
        };
    }

    /**
     * الحصول على أيقونة اليوم
     */
    public function getDayIcon(int $day): string
    {
        if (!$this->weeklySchedule[$day]['is_work_day']) {
            return 'heroicon-o-x-circle';
        }

        return match ($day) {
            5, 6 => 'heroicon-o-sun',
            default => 'heroicon-o-briefcase',
        };
    }

    /**
     * الحصول على معلومات دوام الفرع ليوم معين
     */
    public function getBranchScheduleForDay(int $day): ?array
    {
        return $this->branchSchedule[$day] ?? null;
    }

    /**
     * التحقق من فتح الفرع في يوم معين
     */
    public function isBranchOpen(int $day): bool
    {
        return isset($this->branchSchedule[$day]);
    }

    // ═══════════════════════════════════════════════════════════════
    // إحصائيات
    // ═══════════════════════════════════════════════════════════════

    /**
     * إجمالي أيام العمل
     */
    public function getTotalWorkDays(): int
    {
        $count = 0;
        foreach ($this->weeklySchedule as $data) {
            if ($data['is_work_day']) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * إجمالي ساعات العمل الأسبوعية
     */
    public function getTotalWeeklyHours(): float
    {
        $total = 0;
        foreach ($this->weeklySchedule as $data) {
            $total += $data['effective_hours'];
        }
        return round($total, 1);
    }

    /**
     * إجمالي عدد الشفتات
     */
    public function getTotalShifts(): int
    {
        $count = 0;
        foreach ($this->weeklySchedule as $data) {
            $count += count($data['shifts']);
        }
        return $count;
    }

    // ═══════════════════════════════════════════════════════════════
    // Render
    // ═══════════════════════════════════════════════════════════════

    public function render()
    {
        return view('livewire.weekly-schedule-timeline', [
            'totalWorkDays' => $this->getTotalWorkDays(),
            'totalWeeklyHours' => $this->getTotalWeeklyHours(),
            'totalShifts' => $this->getTotalShifts(),
        ]);
    }
}
