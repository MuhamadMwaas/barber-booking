<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class ManageSalonSchedules extends Page
{
    protected string $view = 'filament.pages.manage-salon-schedules';
    protected static bool $shouldRegisterNavigation = true;

    /**
     * معرف الفرع المحدد (من URL query parameter)
     */
    public ?int $branchId = null;

    /**
     * Mount lifecycle hook
     */
    public function mount(): void
    {
        // استقبال branchId من query parameter
        $this->branchId = request()->query('branchId');
    }

    /**
     * عنوان الصفحة
     */
    public function getTitle(): string|Htmlable
    {
        return __('salon_schedule.page_title');
    }

    /**
     * عنوان التنقل
     */
    public static function getNavigationLabel(): string
    {
        return __('salon_schedule.navigation_label');
    }

    /**
     * مجموعة التنقل (مترجمة)
     */
    public static function getNavigationGroup(): ?string
    {
        return __('salon_schedule.navigation_group');
    }

    /**
     * أيقونة التنقل
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-building-storefront';
    }

    /**
     * ترتيب الصفحة في القائمة
     */
    public static function getNavigationSort(): ?int
    {
        return 30;
    }

    /**
     * صلاحيات الوصول
     */
    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        // Admin يمكنه الوصول دائماً
        if ($user->hasRole('admin')) {
            return true;
        }

        // Manager يمكنه الوصول
        if ($user->hasRole('manager')) {
            return true;
        }

        // التحقق من صلاحية محددة
        if ($user->can('manage salon schedules')) {
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
        return __('salon_schedule.page_heading');
    }

    /**
     * الوصف الفرعي للصفحة
     */
    public function getSubheading(): string|Htmlable|null
    {
        return __('salon_schedule.page_subheading');
    }

    /**
     * أزرار Header Actions
     */
    protected function getHeaderActions(): array
    {
        return [
            // يمكن إضافة أزرار هنا مثل:
            // - تطبيق نفس المواعيد على كل الفروع
            // - استيراد/تصدير المواعيد
        ];
    }

    /**
     * Widget الخاصة بالصفحة
     */
    protected function getHeaderWidgets(): array
    {
        return [];
    }

    /**
     * Widget أسفل الصفحة
     */
    protected function getFooterWidgets(): array
    {
        return [];
    }
}
