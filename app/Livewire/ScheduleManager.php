<?php

namespace App\Livewire;

use App\Models\ProviderScheduledWork;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * ScheduleManager - إدارة جدول شفتات الموظفين
 *
 * هذا المكون يوفر:
 * - عرض أسبوعي للشفتات (7 أيام)
 * - إضافة/حذف شفتات متعددة لكل يوم
 * - التحقق من التعارضات بين الشفتات
 * - نسخ/لصق الشفتات بين الموظفين
 * - نسخ يوم لكل أيام الأسبوع
 * - حفظ atomic داخل transaction
 *
 * القرارات المعمارية:
 * 1. استخدام مصفوفة PHP بدلاً من Collection للشفتات المؤقتة (أسهل للتعديل في Livewire)
 * 2. الحذف وإعادة الإدراج بدلاً من التحديث الجزئي (أبسط وأقل عرضة للأخطاء)
 * 3. التحقق من جانب العميل والخادم (UX أفضل + أمان)
 */
class ScheduleManager extends Component
{
    // ═══════════════════════════════════════════════════════════════
    // الخصائص (Properties)
    // ═══════════════════════════════════════════════════════════════

    /**
     * معرف الموظف المحدد حالياً
     */
    public ?int $selectedUserId = null;

    /**
     * الجدول الأسبوعي للشفتات
     * Structure: [day_of_week => [shift_index => [start_time, end_time, ...]]]
     */
    public array $weeklySchedule = [];

    /**
     * الحافظة للنسخ واللصق
     */
    public array $clipboard = [];

    /**
     * نوع محتوى الحافظة ('day' | 'week' | null)
     */
    public ?string $clipboardType = null;

    /**
     * معرف المستخدم المصدر للنسخ
     */
    public ?int $clipboardSourceUserId = null;

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

    /**
     * الشفت قيد التعديل (للـ modal)
     */
    public ?array $editingShift = null;

    /**
     * اليوم قيد التعديل
     */
    public ?int $editingDay = null;

    /**
     * فهرس الشفت قيد التعديل
     */
    public ?int $editingShiftIndex = null;

    /**
     * قائمة المستخدمين المحددين للنسخ الجماعي
     */
    public array $selectedUsersForBulkPaste = [];

    /**
     * هل نافذة النسخ الجماعي مفتوحة؟
     */
    public bool $showBulkPasteModal = false;

    // ═══════════════════════════════════════════════════════════════
    // قواعد التحقق (Validation Rules)
    // ═══════════════════════════════════════════════════════════════

    protected function rules(): array
    {
        return [
            'selectedUserId' => 'required|exists:users,id',
            'weeklySchedule' => 'array',
            'weeklySchedule.*' => 'array',
            'weeklySchedule.*.*.start_time' => 'required|date_format:H:i',
            'weeklySchedule.*.*.end_time' => 'required|date_format:H:i',
            'weeklySchedule.*.*.is_work_day' => 'boolean',
            'weeklySchedule.*.*.break_minutes' => 'integer|min:0|max:480',
        ];
    }

    protected function messages(): array
    {
        return [
            'selectedUserId.required' => __('schedule.errors.select_provider'),
            'weeklySchedule.*.*.start_time.required' => __('schedule.errors.start_time_required'),
            'weeklySchedule.*.*.end_time.required' => __('schedule.errors.end_time_required'),
            'weeklySchedule.*.*.start_time.date_format' => __('schedule.errors.invalid_time_format'),
            'weeklySchedule.*.*.end_time.date_format' => __('schedule.errors.invalid_time_format'),
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // Lifecycle Hooks
    // ═══════════════════════════════════════════════════════════════

    public function mount(?int $userId = null): void
    {
        $this->initializeEmptyWeek();

        if ($userId) {
            $this->selectedUserId = $userId;
            $this->loadUserSchedule();
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // Computed Properties
    // ═══════════════════════════════════════════════════════════════

    /**
     * قائمة مقدمي الخدمة المتاحين
     */
    #[Computed]
    public function providers(): Collection
    {
        return User::role('provider')
            ->where('is_active', true)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name']);
    }

    /**
     * قائمة أيام الأسبوع للعرض
     */
    #[Computed]
    public function days(): array
    {
        $locale = app()->getLocale();
        $days = [];

        foreach (ProviderScheduledWork::DAYS as $num => $name) {
            $days[$num] = [
                'number' => $num,
                'name' => $name,
                'name_ar' => ProviderScheduledWork::DAYS[$num],
                'short' => ProviderScheduledWork::DAYS[$num],
                'short_ar' => ProviderScheduledWork::DAYS[$num],
                'localized' => $locale === 'ar'
                    ? ProviderScheduledWork::DAYS[$num]
                    : $name,
            ];
        }

        return $days;
    }

    /**
     * الموظف المحدد حالياً
     */
    #[Computed]
    public function selectedProvider(): ?User
    {
        if (!$this->selectedUserId) {
            return null;
        }

        return User::find($this->selectedUserId);
    }

    /**
     * إجمالي ساعات العمل الأسبوعية
     */
    #[Computed]
    public function totalWeeklyHours(): float
    {
        $totalMinutes = 0;

        foreach ($this->weeklySchedule as $dayShifts) {
            foreach ($dayShifts as $shift) {
                if (!empty($shift['is_work_day']) && !empty($shift['start_time']) && !empty($shift['end_time'])) {
                    $duration = ProviderScheduledWork::timeToMinutes($shift['end_time'])
                        - ProviderScheduledWork::timeToMinutes($shift['start_time']);

                    if ($duration < 0) {
                        $duration += 24 * 60; // شفت يمتد لليوم التالي
                    }

                    $breakMinutes = $shift['break_minutes'] ?? 0;
                    $totalMinutes += max(0, $duration - $breakMinutes);
                }
            }
        }

        return round($totalMinutes / 60, 2);
    }

    // ═══════════════════════════════════════════════════════════════
    // إدارة اختيار الموظف
    // ═══════════════════════════════════════════════════════════════

    /**
     * تغيير الموظف المحدد
     */
    public function updatedSelectedUserId(): void
    {
        if ($this->hasUnsavedChanges) {
            // يمكن إضافة تأكيد هنا قبل التبديل
            // لكن للبساطة سنقوم بالتحميل مباشرة
        }

        $this->clearMessages();
        $this->loadUserSchedule();
    }

    /**
     * تحميل جدول الموظف من قاعدة البيانات
     */
    public function loadUserSchedule(): void
    {
        $this->initializeEmptyWeek();

        if (!$this->selectedUserId) {
            return;
        }

        $shifts = ProviderScheduledWork::forUser($this->selectedUserId)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        foreach ($shifts as $shift) {
            $this->weeklySchedule[$shift->day_of_week][] = [
                'id' => $shift->id,
                'start_time' => substr($shift->start_time, 0, 5), // تحويل H:i:s إلى H:i
                'end_time' => substr($shift->end_time, 0, 5),
                'is_work_day' => $shift->is_work_day,
                'break_minutes' => $shift->break_minutes,
                'notes' => $shift->notes ?? '',
                'is_active' => $shift->is_active,
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
            $this->weeklySchedule[$i] = [];
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // إدارة الشفتات (CRUD)
    // ═══════════════════════════════════════════════════════════════

    /**
     * إضافة شفت جديد ليوم معين
     */
    public function addShift(int $day): void
    {
        if ($day < 0 || $day > 6) {
            return;
        }

        // قيم افتراضية ذكية
        $defaultStart = '09:00';
        $defaultEnd = '17:00';

        // إذا كان هناك شفتات أخرى في نفس اليوم، نقترح وقت بعد آخر شفت
        $existingShifts = $this->weeklySchedule[$day] ?? [];
        if (!empty($existingShifts)) {
            $lastShift = end($existingShifts);
            $lastEndMinutes = ProviderScheduledWork::timeToMinutes($lastShift['end_time']);

            // ابدأ الشفت الجديد بعد 30 دقيقة من نهاية الشفت السابق
            $newStartMinutes = $lastEndMinutes + 30;
            $newEndMinutes = $newStartMinutes + (4 * 60); // 4 ساعات افتراضي

            if ($newStartMinutes < 24 * 60) { // تأكد أنه لا يتجاوز منتصف الليل
                $defaultStart = ProviderScheduledWork::minutesToTime($newStartMinutes);
                $defaultEnd = ProviderScheduledWork::minutesToTime(min($newEndMinutes, 23 * 60 + 59));
            }
        }

        $this->weeklySchedule[$day][] = [
            'id' => null, // جديد، بدون ID
            'start_time' => $defaultStart,
            'end_time' => $defaultEnd,
            'is_work_day' => true,
            'break_minutes' => 0,
            'notes' => '',
            'is_active' => true,
        ];

        $this->hasUnsavedChanges = true;
        $this->clearMessages();
    }

    /**
     * حذف شفت من يوم معين
     */
    public function removeShift(int $day, int $index): void
    {
        if (isset($this->weeklySchedule[$day][$index])) {
            array_splice($this->weeklySchedule[$day], $index, 1);
            // إعادة فهرسة المصفوفة
            $this->weeklySchedule[$day] = array_values($this->weeklySchedule[$day]);
            $this->hasUnsavedChanges = true;
            $this->clearMessages();
        }
    }

    /**
     * فتح نافذة تعديل شفت
     */
    public function editShift(int $day, int $index): void
    {
        if (isset($this->weeklySchedule[$day][$index])) {
            $this->editingDay = $day;
            $this->editingShiftIndex = $index;
            $this->editingShift = $this->weeklySchedule[$day][$index];

            $this->dispatch('open-modal', id: 'edit-shift-modal');
        }
    }

    /**
     * حفظ تعديلات الشفت
     */
    public function saveShiftEdit(): void
    {
        if ($this->editingDay !== null && $this->editingShiftIndex !== null && $this->editingShift) {
            $this->weeklySchedule[$this->editingDay][$this->editingShiftIndex] = $this->editingShift;
            $this->hasUnsavedChanges = true;
            $this->closeEditModal();
        }
    }

    /**
     * إغلاق نافذة التعديل
     */
    public function closeEditModal(): void
    {
        $this->editingDay = null;
        $this->editingShiftIndex = null;
        $this->editingShift = null;

        $this->dispatch('close-modal', id: 'edit-shift-modal');
    }

    /**
     * تبديل حالة يوم العمل
     */
    public function toggleWorkDay(int $day, int $index): void
    {
        if (isset($this->weeklySchedule[$day][$index])) {
            $this->weeklySchedule[$day][$index]['is_work_day'] =
                !$this->weeklySchedule[$day][$index]['is_work_day'];
            $this->hasUnsavedChanges = true;
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // النسخ واللصق
    // ═══════════════════════════════════════════════════════════════

    /**
     * نسخ شفتات يوم معين
     */
    public function copyDay(int $day): void
    {
        if (!isset($this->weeklySchedule[$day])) {
            return;
        }

        $this->clipboard = $this->weeklySchedule[$day];
        $this->clipboardType = 'day';
        $this->clipboardSourceUserId = $this->selectedUserId;

        $dayName = $this->days[$day]['localized'] ?? "Day $day";
        $this->successMessage = __('schedule.messages.day_copied', ['day' => $dayName]);
    }

    /**
     * لصق الشفتات المنسوخة ليوم معين
     */
    public function pasteDay(int $day): void
    {
        if (empty($this->clipboard) || $this->clipboardType !== 'day') {
            $this->errors[] = __('schedule.errors.nothing_to_paste');
            return;
        }

        // نسخ عميق للشفتات مع إزالة الـ IDs
        $this->weeklySchedule[$day] = array_map(function ($shift) {
            $newShift = $shift;
            $newShift['id'] = null; // إزالة ID لإنشاء سجل جديد
            return $newShift;
        }, $this->clipboard);

        $this->hasUnsavedChanges = true;

        $dayName = $this->days[$day]['localized'] ?? "Day $day";
        $this->successMessage = __('schedule.messages.day_pasted', ['day' => $dayName]);
    }

    /**
     * نسخ كل أيام الأسبوع
     */
    public function copyWeek(): void
    {
        if (!$this->selectedUserId) {
            $this->errors[] = __('schedule.errors.select_provider_first');
            return;
        }

        $this->clipboard = $this->weeklySchedule;
        $this->clipboardType = 'week';
        $this->clipboardSourceUserId = $this->selectedUserId;

        $this->successMessage = __('schedule.messages.week_copied');
    }

    /**
     * نسخ من موظف آخر
     */
    public function copyFromUser(int $userId): void
    {
        if (!$userId) {
            return;
        }

        $shifts = ProviderScheduledWork::forUser($userId)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        $weekSchedule = [];
        for ($i = 0; $i < 7; $i++) {
            $weekSchedule[$i] = [];
        }

        foreach ($shifts as $shift) {
            $weekSchedule[$shift->day_of_week][] = [
                'id' => null, // لا ننسخ الـ ID
                'start_time' => substr($shift->start_time, 0, 5),
                'end_time' => substr($shift->end_time, 0, 5),
                'is_work_day' => $shift->is_work_day,
                'break_minutes' => $shift->break_minutes,
                'notes' => $shift->notes ?? '',
                'is_active' => $shift->is_active,
            ];
        }

        $this->clipboard = $weekSchedule;
        $this->clipboardType = 'week';
        $this->clipboardSourceUserId = $userId;

        $user = User::find($userId);
        $this->successMessage = __('schedule.messages.copied_from_user', [
            'name' => $user?->full_name ?? "User #$userId"
        ]);
    }

    /**
     * لصق الأسبوع المنسوخ للموظف الحالي
     */
    public function pasteToCurrentUser(): void
    {
        if (empty($this->clipboard) || $this->clipboardType !== 'week') {
            $this->errors[] = __('schedule.errors.nothing_to_paste');
            return;
        }

        if (!$this->selectedUserId) {
            $this->errors[] = __('schedule.errors.select_provider_first');
            return;
        }

        // نسخ عميق مع إزالة IDs
        foreach ($this->clipboard as $day => $shifts) {
            $this->weeklySchedule[$day] = array_map(function ($shift) {
                $newShift = $shift;
                $newShift['id'] = null;
                return $newShift;
            }, $shifts);
        }

        $this->hasUnsavedChanges = true;
        $this->successMessage = __('schedule.messages.week_pasted');
    }

    /**
     * تطبيق شفتات يوم على كل أيام الأسبوع
     */
    public function applyDayToAllWeek(int $sourceDay): void
    {
        if (!isset($this->weeklySchedule[$sourceDay])) {
            return;
        }

        $sourceShifts = $this->weeklySchedule[$sourceDay];

        for ($day = 0; $day < 7; $day++) {
            if ($day !== $sourceDay) {
                $this->weeklySchedule[$day] = array_map(function ($shift) {
                    $newShift = $shift;
                    $newShift['id'] = null;
                    return $newShift;
                }, $sourceShifts);
            }
        }

        $this->hasUnsavedChanges = true;

        $dayName = $this->days[$sourceDay]['localized'] ?? "Day $sourceDay";
        $this->successMessage = __('schedule.messages.day_applied_to_week', ['day' => $dayName]);
    }

    /**
     * فتح نافذة اللصق الجماعي
     */
    public function openBulkPasteModal(): void
    {
        if (empty($this->clipboard) || $this->clipboardType !== 'week') {
            $this->errors[] = __('schedule.errors.copy_week_first');
            return;
        }

        $this->selectedUsersForBulkPaste = [];
        $this->showBulkPasteModal = true;
    }

    /**
     * إغلاق نافذة اللصق الجماعي
     */
    public function closeBulkPasteModal(): void
    {
        $this->showBulkPasteModal = false;
        $this->selectedUsersForBulkPaste = [];
    }

    /**
     * تطبيق الأسبوع على موظفين محددين
     */
    public function applyWeekToUsers(): void
    {
        if (empty($this->clipboard) || $this->clipboardType !== 'week') {
            $this->errors[] = __('schedule.errors.copy_week_first');
            return;
        }

        if (empty($this->selectedUsersForBulkPaste)) {
            $this->errors[] = __('schedule.errors.select_users_to_paste');
            return;
        }

        $successCount = 0;
        $failCount = 0;

        DB::beginTransaction();

        try {
            foreach ($this->selectedUsersForBulkPaste as $userId) {
                // تخطي المستخدم الحالي إذا كان محدداً (سيتم حفظه بشكل منفصل)
                if ($userId == $this->selectedUserId) {
                    continue;
                }

                // حذف الشفتات القديمة
                ProviderScheduledWork::forUser($userId)->delete();

                // إدراج الشفتات الجديدة
                foreach ($this->clipboard as $day => $shifts) {
                    foreach ($shifts as $shift) {
                        ProviderScheduledWork::create([
                            'user_id' => $userId,
                            'day_of_week' => $day,
                            'start_time' => $shift['start_time'],
                            'end_time' => $shift['end_time'],
                            'is_work_day' => $shift['is_work_day'] ?? true,
                            'break_minutes' => $shift['break_minutes'] ?? 0,
                            'notes' => $shift['notes'] ?? null,
                            'is_active' => $shift['is_active'] ?? true,
                        ]);
                    }
                }

                $successCount++;
            }

            DB::commit();

            $this->successMessage = __('schedule.messages.bulk_paste_success', ['count' => $successCount]);
            $this->closeBulkPasteModal();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk paste failed: ' . $e->getMessage());
            $this->errors[] = __('schedule.errors.bulk_paste_failed');
        }
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

        foreach ($this->weeklySchedule as $day => $shifts) {
            $dayName = $this->days[$day]['localized'] ?? "Day $day";

            foreach ($shifts as $index => $shift) {
                $shiftNum = $index + 1;

                // التحقق من وجود الأوقات
                if (empty($shift['start_time'])) {
                    $errors[] = __('schedule.errors.shift_missing_start', [
                        'day' => $dayName,
                        'shift' => $shiftNum
                    ]);
                    continue;
                }

                if (empty($shift['end_time'])) {
                    $errors[] = __('schedule.errors.shift_missing_end', [
                        'day' => $dayName,
                        'shift' => $shiftNum
                    ]);
                    continue;
                }

                // التحقق من أن البداية قبل النهاية
                $startMinutes = ProviderScheduledWork::timeToMinutes($shift['start_time']);
                $endMinutes = ProviderScheduledWork::timeToMinutes($shift['end_time']);

                if ($startMinutes >= $endMinutes) {
                    // السماح بالشفتات الليلية التي تمتد لليوم التالي إذا كان الفرق كبير
                    if ($startMinutes - $endMinutes > 12 * 60) {
                        // هذا شفت ليلي صحيح (مثل 22:00 - 06:00)
                    } else {
                        $errors[] = __('schedule.errors.shift_invalid_time_range', [
                            'day' => $dayName,
                            'shift' => $shiftNum,
                            'start' => $shift['start_time'],
                            'end' => $shift['end_time']
                        ]);
                    }
                }
            }

            // التحقق من التعارضات بين شفتات نفس اليوم
            $dayOverlaps = $this->findDayOverlaps($day, $shifts);
            foreach ($dayOverlaps as $overlap) {
                $errors[] = __('schedule.errors.shifts_overlap', [
                    'day' => $dayName,
                    'shift1' => ($overlap['index1'] + 1),
                    'shift2' => ($overlap['index2'] + 1),
                    'time1' => "{$overlap['start1']} - {$overlap['end1']}",
                    'time2' => "{$overlap['start2']} - {$overlap['end2']}"
                ]);
            }
        }

        return $errors;
    }

    /**
     * البحث عن التعارضات في يوم معين
     */
    protected function findDayOverlaps(int $day, array $shifts): array
    {
        $overlaps = [];
        $count = count($shifts);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $shift1 = $shifts[$i];
                $shift2 = $shifts[$j];

                // تخطي الشفتات غير الفعالة
                if (empty($shift1['is_work_day']) || empty($shift2['is_work_day'])) {
                    continue;
                }

                if (ProviderScheduledWork::shiftsOverlap(
                    $shift1['start_time'] ?? '00:00',
                    $shift1['end_time'] ?? '00:00',
                    $shift2['start_time'] ?? '00:00',
                    $shift2['end_time'] ?? '00:00'
                )) {
                    $overlaps[] = [
                        'index1' => $i,
                        'index2' => $j,
                        'start1' => $shift1['start_time'],
                        'end1' => $shift1['end_time'],
                        'start2' => $shift2['start_time'],
                        'end2' => $shift2['end_time'],
                    ];
                }
            }
        }

        return $overlaps;
    }

    /**
     * حفظ كل الشفتات
     *
     * استراتيجية الحفظ (Atomic):
     * 1. التحقق من صحة كل البيانات أولاً
     * 2. بدء transaction
     * 3. حذف كل شفتات المستخدم القديمة
     * 4. إدراج الشفتات الجديدة
     * 5. commit أو rollback
     *
     * لماذا delete & reinsert بدلاً من upsert؟
     * - أبسط وأقل عرضة للأخطاء
     * - يتعامل تلقائياً مع الشفتات المحذوفة
     * - لا حاجة لتتبع الـ IDs
     */
    public function saveAll(): void
    {
        $this->clearMessages();

        // التحقق من اختيار موظف
        if (!$this->selectedUserId) {
            $this->errors[] = __('schedule.errors.select_provider_first');
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
            // حذف كل الشفتات القديمة للمستخدم
            ProviderScheduledWork::forUser($this->selectedUserId)->delete();

            // إدراج الشفتات الجديدة
            $insertedCount = 0;

            foreach ($this->weeklySchedule as $day => $shifts) {
                foreach ($shifts as $index => $shift) {
                    // تخطي الشفتات الفارغة
                    if (empty($shift['start_time']) || empty($shift['end_time'])) {
                        continue;
                    }

                    ProviderScheduledWork::create([
                        'user_id' => $this->selectedUserId,
                        'day_of_week' => $day,
                        'start_time' => $shift['start_time'],
                        'end_time' => $shift['end_time'],
                        'is_work_day' => $shift['is_work_day'] ?? true,
                        'break_minutes' => $shift['break_minutes'] ?? 0,
                        'notes' => $shift['notes'] ?? null,
                        'is_active' => $shift['is_active'] ?? true,
                    ]);

                    $insertedCount++;
                }
            }

            DB::commit();

            // إعادة تحميل البيانات للتأكد من تحديث الـ IDs
            $this->loadUserSchedule();

            $this->hasUnsavedChanges = false;
            $this->successMessage = __('schedule.messages.saved_successfully', [
                'count' => $insertedCount
            ]);

            // إرسال حدث للتحديث
            $this->dispatch('schedule-saved', userId: $this->selectedUserId);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Schedule save failed: ' . $e->getMessage(), [
                'user_id' => $this->selectedUserId,
                'trace' => $e->getTraceAsString()
            ]);

            $this->errors[] = __('schedule.errors.save_failed') . ': ' . $e->getMessage();
        }
    }

    /**
     * إعادة تحميل البيانات (تجاهل التغييرات)
     */
    public function resetSchedule(): void
    {
        $this->loadUserSchedule();
        $this->clearMessages();
        $this->successMessage = __('schedule.messages.reset_successfully');
    }

    /**
     * مسح كل شفتات الأسبوع
     */
    public function clearAllShifts(): void
    {
        $this->initializeEmptyWeek();
        $this->hasUnsavedChanges = true;
        $this->successMessage = __('schedule.messages.all_cleared');
    }

    /**
     * مسح شفتات يوم معين
     */
    public function clearDay(int $day): void
    {
        if (isset($this->weeklySchedule[$day])) {
            $this->weeklySchedule[$day] = [];
            $this->hasUnsavedChanges = true;

            $dayName = $this->days[$day]['localized'] ?? "Day $day";
            $this->successMessage = __('schedule.messages.day_cleared', ['day' => $dayName]);
        }
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
     * الحصول على عدد الشفتات في يوم
     */
    public function getShiftCount(int $day): int
    {
        return count($this->weeklySchedule[$day] ?? []);
    }

    /**
     * التحقق من وجود شفتات في يوم
     */
    public function hasShifts(int $day): bool
    {
        return !empty($this->weeklySchedule[$day]);
    }

    /**
     * الحصول على إجمالي ساعات يوم معين
     */
    public function getDayHours(int $day): float
    {
        $totalMinutes = 0;

        foreach ($this->weeklySchedule[$day] ?? [] as $shift) {
            if (!empty($shift['is_work_day']) && !empty($shift['start_time']) && !empty($shift['end_time'])) {
                $duration = ProviderScheduledWork::timeToMinutes($shift['end_time'])
                    - ProviderScheduledWork::timeToMinutes($shift['start_time']);

                if ($duration < 0) {
                    $duration += 24 * 60;
                }

                $breakMinutes = $shift['break_minutes'] ?? 0;
                $totalMinutes += max(0, $duration - $breakMinutes);
            }
        }

        return round($totalMinutes / 60, 2);
    }

    // ═══════════════════════════════════════════════════════════════
    // Render
    // ═══════════════════════════════════════════════════════════════

    public function render()
    {
        return view('livewire.schedule-manager');
    }
}
