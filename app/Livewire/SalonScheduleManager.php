<?php

namespace App\Livewire;

use App\Models\Branch;
use App\Models\SalonSchedule;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * SalonScheduleManager - إدارة جدول مواعيد الصالون
 *
 * هذا المكون يوفر:
 * - عرض أسبوعي لمواعيد الصالون (7 أيام)
 * - تحديد أوقات الفتح والإغلاق لكل يوم
 * - عرض Timeline مرئي لكل يوم
 * - نسخ مواعيد بين الأيام
 * - حفظ atomic داخل transaction
 */
class SalonScheduleManager extends Component
{
    // ═══════════════════════════════════════════════════════════════
    // الخصائص (Properties)
    // ═══════════════════════════════════════════════════════════════

    /**
     * معرف الفرع المحدد حالياً
     */
    public ?int $selectedBranchId = null;

    /**
     * الجدول الأسبوعي للصالون
     * Structure: [day_of_week => [id, open_time, close_time, is_open]]
     */
    public array $weeklySchedule = [];

    /**
     * الحافظة للنسخ واللصق
     */
    public array $clipboard = [];

    /**
     * نوع محتوى الحافظة ('day' | null)
     */
    public ?string $clipboardType = null;

    /**
     * رسائل الخطأ
     */
    public array $errors = [];

    /**
     * رسائل النجاح
     */
    public string $successMessage = '';

    /**
     * هل تم تعديل البيانات ولم تُحفظ؟
     */
    public bool $hasUnsavedChanges = false;

    // ═══════════════════════════════════════════════════════════════
    // قواعد التحقق (Validation Rules)
    // ═══════════════════════════════════════════════════════════════

    protected function rules(): array
    {
        return [
            'selectedBranchId' => 'required|exists:branches,id',
            'weeklySchedule' => 'array',
            'weeklySchedule.*.open_time' => 'nullable|date_format:H:i',
            'weeklySchedule.*.close_time' => 'nullable|date_format:H:i',
            'weeklySchedule.*.is_open' => 'boolean',
        ];
    }

    protected function messages(): array
    {
        return [
            'selectedBranchId.required' => __('salon_schedule.please_select_branch'),
            'weeklySchedule.*.open_time.date_format' => __('salon_schedule.validation.invalid_time_format'),
            'weeklySchedule.*.close_time.date_format' => __('salon_schedule.validation.invalid_time_format'),
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // Lifecycle Hooks
    // ═══════════════════════════════════════════════════════════════

    public function mount(?int $branchId = null): void
    {
        $this->initializeEmptyWeek();

        if ($branchId) {
            $this->selectedBranchId = $branchId;
            $this->loadBranchSchedule();
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // Computed Properties
    // ═══════════════════════════════════════════════════════════════

    /**
     * قائمة الفروع المتاحة
     */
    #[Computed]
    public function branches(): Collection
    {
        return Branch::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'adress']);
    }

    /**
     * قائمة أيام الأسبوع للعرض
     */
    #[Computed]
    public function days(): array
    {
        $locale = app()->getLocale();
        $days = [];

        $daysAr = [
            0 => 'الأحد',
            1 => 'الاثنين',
            2 => 'الثلاثاء',
            3 => 'الأربعاء',
            4 => 'الخميس',
            5 => 'الجمعة',
            6 => 'السبت',
        ];

        foreach (SalonSchedule::DAYS as $num => $name) {
            $days[$num] = [
                'number' => $num,
                'name' => $name,
                'name_ar' => $daysAr[$num],
                'localized' => $locale === 'ar' ? $daysAr[$num] : $name,
            ];
        }

        return $days;
    }

    /**
     * الفرع المحدد حالياً
     */
    #[Computed]
    public function selectedBranch(): ?Branch
    {
        if (!$this->selectedBranchId) {
            return null;
        }

        return Branch::find($this->selectedBranchId);
    }

    /**
     * إجمالي ساعات العمل الأسبوعية
     */
    #[Computed]
    public function totalWeeklyHours(): float
    {
        $totalMinutes = 0;

        foreach ($this->weeklySchedule as $daySchedule) {
            if (!empty($daySchedule['is_open']) && !empty($daySchedule['open_time']) && !empty($daySchedule['close_time'])) {
                $duration = $this->timeToMinutes($daySchedule['close_time'])
                    - $this->timeToMinutes($daySchedule['open_time']);

                if ($duration < 0) {
                    $duration += 24 * 60; // يمتد لليوم التالي
                }

                $totalMinutes += max(0, $duration);
            }
        }

        return round($totalMinutes / 60, 2);
    }

    /**
     * عدد أيام العمل
     */
    #[Computed]
    public function openDaysCount(): int
    {
        $count = 0;
        foreach ($this->weeklySchedule as $daySchedule) {
            if (!empty($daySchedule['is_open'])) {
                $count++;
            }
        }
        return $count;
    }

    // ═══════════════════════════════════════════════════════════════
    // إدارة اختيار الفرع
    // ═══════════════════════════════════════════════════════════════

    /**
     * تغيير الفرع المحدد
     */
    public function updatedSelectedBranchId(): void
    {
        if ($this->hasUnsavedChanges) {
            // يمكن إضافة تأكيد هنا قبل التبديل
        }

        $this->clearMessages();
        $this->loadBranchSchedule();
    }

    /**
     * تحميل جدول الفرع من قاعدة البيانات
     */
    public function loadBranchSchedule(): void
    {
        $this->initializeEmptyWeek();

        if (!$this->selectedBranchId) {
            return;
        }

        $schedules = SalonSchedule::where('branch_id', $this->selectedBranchId)
            ->orderBy('day_of_week')
            ->get();

        foreach ($schedules as $schedule) {
            $this->weeklySchedule[$schedule->day_of_week] = [
                'id' => $schedule->id,
                'open_time' => substr($schedule->open_time, 0, 5), // H:i:s إلى H:i
                'close_time' => substr($schedule->close_time, 0, 5),
                'is_open' => $schedule->is_open,
            ];
        }

        $this->hasUnsavedChanges = false;
    }

    /**
     * تهيئة أسبوع فارغ
     */
    protected function initializeEmptyWeek(): void
    {
        $this->weeklySchedule = [];
        for ($i = 0; $i < 7; $i++) {
            $this->weeklySchedule[$i] = [
                'id' => null,
                'open_time' => '09:00',
                'close_time' => '21:00',
                'is_open' => true,
            ];
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // إدارة الجدول
    // ═══════════════════════════════════════════════════════════════

    /**
     * تبديل حالة فتح/إغلاق اليوم
     */
    public function toggleDayOpen(int $day): void
    {
        if (isset($this->weeklySchedule[$day])) {
            $this->weeklySchedule[$day]['is_open'] = !$this->weeklySchedule[$day]['is_open'];
            $this->hasUnsavedChanges = true;
            $this->clearMessages();
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // النسخ واللصق
    // ═══════════════════════════════════════════════════════════════

    /**
     * نسخ مواعيد يوم معين
     */
    public function copyDay(int $day): void
    {
        if (!isset($this->weeklySchedule[$day])) {
            return;
        }

        $this->clipboard = $this->weeklySchedule[$day];
        $this->clipboardType = 'day';

        $dayName = $this->days[$day]['localized'] ?? "Day $day";
        $this->successMessage = __('salon_schedule.messages.day_copied', ['day' => $dayName]);
    }

    /**
     * لصق المواعيد المنسوخة ليوم معين
     */
    public function pasteDay(int $day): void
    {
        if (empty($this->clipboard) || $this->clipboardType !== 'day') {
            $this->errors[] = __('salon_schedule.errors.nothing_to_paste');
            return;
        }

        $this->weeklySchedule[$day] = [
            'id' => $this->weeklySchedule[$day]['id'] ?? null, // نحتفظ بالـ ID الحالي
            'open_time' => $this->clipboard['open_time'],
            'close_time' => $this->clipboard['close_time'],
            'is_open' => $this->clipboard['is_open'],
        ];

        $this->hasUnsavedChanges = true;

        $dayName = $this->days[$day]['localized'] ?? "Day $day";
        $this->successMessage = __('salon_schedule.messages.day_pasted', ['day' => $dayName]);
    }

    /**
     * تطبيق مواعيد يوم على كل أيام الأسبوع
     */
    public function applyDayToAllWeek(int $sourceDay): void
    {
        if (!isset($this->weeklySchedule[$sourceDay])) {
            return;
        }

        $sourceSchedule = $this->weeklySchedule[$sourceDay];

        for ($day = 0; $day < 7; $day++) {
            if ($day !== $sourceDay) {
                $this->weeklySchedule[$day] = [
                    'id' => $this->weeklySchedule[$day]['id'] ?? null,
                    'open_time' => $sourceSchedule['open_time'],
                    'close_time' => $sourceSchedule['close_time'],
                    'is_open' => $sourceSchedule['is_open'],
                ];
            }
        }

        $this->hasUnsavedChanges = true;

        $dayName = $this->days[$sourceDay]['localized'] ?? "Day $sourceDay";
        $this->successMessage = __('salon_schedule.messages.day_applied_to_week', ['day' => $dayName]);
    }

    // ═══════════════════════════════════════════════════════════════
    // التحقق والحفظ
    // ═══════════════════════════════════════════════════════════════

    /**
     * التحقق من صحة البيانات
     */
    protected function validateSchedule(): array
    {
        $errors = [];

        foreach ($this->weeklySchedule as $day => $schedule) {
            if (!$schedule['is_open']) {
                continue;
            }

            $dayName = $this->days[$day]['localized'] ?? "Day $day";

            // التحقق من وجود الأوقات
            if (empty($schedule['open_time'])) {
                $errors[] = __('salon_schedule.open_time_required', ['day' => $dayName]);
                continue;
            }

            if (empty($schedule['close_time'])) {
                $errors[] = __('salon_schedule.close_time_required', ['day' => $dayName]);
                continue;
            }

            // التحقق من أن وقت الإغلاق مختلف عن وقت الفتح
            if ($schedule['open_time'] === $schedule['close_time']) {
                $errors[] = __('salon_schedule.validation.close_time_must_differ') . " ($dayName)";
            }
        }

        return $errors;
    }

    /**
     * حفظ كل المواعيد
     */
    public function saveAll(): void
    {
        $this->clearMessages();

        // التحقق من اختيار فرع
        if (!$this->selectedBranchId) {
            $this->errors[] = __('salon_schedule.please_select_branch');
            return;
        }

        // التحقق من صحة البيانات
        $validationErrors = $this->validateSchedule();
        if (!empty($validationErrors)) {
            $this->errors = $validationErrors;
            return;
        }

        DB::beginTransaction();

        try {
            foreach ($this->weeklySchedule as $day => $schedule) {
                SalonSchedule::updateOrCreate(
                    [
                        'branch_id' => $this->selectedBranchId,
                        'day_of_week' => $day,
                    ],
                    [
                        'open_time' => $schedule['open_time'] ?? '09:00',
                        'close_time' => $schedule['close_time'] ?? '21:00',
                        'is_open' => $schedule['is_open'] ?? false,
                    ]
                );
            }

            DB::commit();

            // إعادة تحميل البيانات
            $this->loadBranchSchedule();

            $this->hasUnsavedChanges = false;
            $this->successMessage = __('salon_schedule.schedule_saved_successfully');

            // إرسال إشعار نجاح
            Notification::make()
                ->success()
                ->title(__('salon_schedule.schedule_saved'))
                ->body(__('salon_schedule.schedule_saved_successfully'))
                ->send();

            // إرسال حدث للتحديث
            $this->dispatch('salon-schedule-saved', branchId: $this->selectedBranchId);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Salon schedule save failed: ' . $e->getMessage(), [
                'branch_id' => $this->selectedBranchId,
                'trace' => $e->getTraceAsString()
            ]);

            $this->errors[] = __('salon_schedule.save_error') . ': ' . $e->getMessage();

            // إرسال إشعار خطأ
            Notification::make()
                ->danger()
                ->title(__('salon_schedule.save_error'))
                ->body($e->getMessage())
                ->send();
        }
    }

    /**
     * إعادة تحميل البيانات (تجاهل التغييرات)
     */
    public function resetSchedule(): void
    {
        $this->loadBranchSchedule();
        $this->clearMessages();
        $this->successMessage = __('salon_schedule.schedule_reloaded');
    }

    // ═══════════════════════════════════════════════════════════════
    // دوال مساعدة
    // ═══════════════════════════════════════════════════════════════

    /**
     * مسح رسائل الخطأ والنجاح
     */
    protected function clearMessages(): void
    {
        $this->errors = [];
        $this->successMessage = '';
    }

    /**
     * تحويل وقت إلى دقائق
     */
    protected function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = explode(':', $time);
        return (int)$hours * 60 + (int)$minutes;
    }

    /**
     * الحصول على ساعات يوم معين
     */
    public function getDayHours(int $day): float
    {
        if (!isset($this->weeklySchedule[$day]) || !$this->weeklySchedule[$day]['is_open']) {
            return 0;
        }

        $schedule = $this->weeklySchedule[$day];

        if (empty($schedule['open_time']) || empty($schedule['close_time'])) {
            return 0;
        }

        $duration = $this->timeToMinutes($schedule['close_time'])
            - $this->timeToMinutes($schedule['open_time']);

        if ($duration < 0) {
            $duration += 24 * 60;
        }

        return round($duration / 60, 2);
    }

    /**
     * الحصول على نسبة الوقت في اليوم (للـ Timeline)
     */
    public function getTimelinePosition(string $time): float
    {
        $minutes = $this->timeToMinutes($time);
        return ($minutes / (24 * 60)) * 100;
    }

    /**
     * الحصول على عرض شريط العمل في Timeline
     */
    public function getTimelineWidth(int $day): float
    {
        if (!isset($this->weeklySchedule[$day]) || !$this->weeklySchedule[$day]['is_open']) {
            return 0;
        }

        $schedule = $this->weeklySchedule[$day];

        if (empty($schedule['open_time']) || empty($schedule['close_time'])) {
            return 0;
        }

        $startPos = $this->getTimelinePosition($schedule['open_time']);
        $endPos = $this->getTimelinePosition($schedule['close_time']);

        if ($endPos < $startPos) {
            $endPos += 100; // يمتد لليوم التالي
        }

        return $endPos - $startPos;
    }

    // ═══════════════════════════════════════════════════════════════
    // Render
    // ═══════════════════════════════════════════════════════════════

    public function render()
    {
        return view('livewire.salon-schedule-manager');
    }
}
