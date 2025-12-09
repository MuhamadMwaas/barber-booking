<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class ManageProviderSchedules extends Page
{
    protected  string $view = 'filament.pages.manage-provider-schedules';
    protected static bool $shouldRegisterNavigation = true;
    /**
     * معرف الموظف المحدد (من URL query parameter)
     */
    public ?int $userId = null;

    /**
     * Mount lifecycle hook
     */
    public function mount(): void
    {

        // استقبال userId من query parameter
        $this->userId = request()->query('userId');
    }

    /**
     * ترتيب الصفحة في القائمة
     */

    /**
     * مجموعة التنقل
     */
    // protected static ?string $navigationGroup = 'Provider Management';

    /**
     * عنوان الصفحة
     */
    public function getTitle(): string|Htmlable
    {
        return __('schedule.page_title');
    }

    /**
     * عنوان التنقل
     */
    public static function getNavigationLabel(): string
    {
        return __('schedule.navigation_label2');
    }

    /**
     * مجموعة التنقل (مترجمة)
     */
    public static function getNavigationGroup(): ?string
    {
        return __('schedule.navigation_group');
    }


    public static function canAccess(): bool
    {
        $user = Auth::user();
          return true; // للسماح للجميع بالوصول مؤقتاً
        if (!$user) {
            return false;
        }

        // Admin يمكنه الوصول دائماً
        if ($user->hasRole('admin')) {
            return true;
        }

        // التحقق من صلاحية محددة
        if ($user->can('manage provider schedules')) {
            return true;
        }

        // Manager يمكنه الوصول
        if ($user->hasRole('manager')) {
            return true;
        }

        return false;
    }

    /**
     * بيانات الـ Breadcrumbs
     */
    public function getBreadcrumbs(): array
    {
        return [
            route('filament.admin.pages.dashboard') => __('filament-panels::pages/dashboard.title'),
            static::getUrl() => static::getNavigationLabel(),
        ];
    }

    /**
     * تحديث عنوان الصفحة في المتصفح
     */
    public function getHeading(): string|Htmlable
    {
        return __('schedule.page_heading');
    }

    /**
     * الوصف الفرعي للصفحة
     */
    public function getSubheading(): string|Htmlable|null
    {
        return __('schedule.page_subheading');
    }

    /**
     * أزرار Header Actions (اختياري)
     */
    protected function getHeaderActions(): array
    {
        return [
            // يمكن إضافة أزرار هنا مثل:
            // - تصدير الجداول
            // - استيراد من ملف
            // - إعدادات افتراضية
        ];
    }

    /**
     * Widget الخاصة بالصفحة (اختياري)
     */
    protected function getHeaderWidgets(): array
    {
        return [
            // يمكن إضافة widgets هنا مثل:
            // - إحصائيات الشفتات
            // - التقويم الشهري
        ];
    }

    /**
     * Widget أسفل الصفحة (اختياري)
     */
    protected function getFooterWidgets(): array
    {
        return [];
    }
}
