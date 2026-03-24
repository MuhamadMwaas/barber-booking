<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

trait NavigationDefaultAccess {

    protected static function superAdminRole(): string {
        return 'SuperAdmin';
    }


    protected static function permissionPrefix(): string {
        $base = class_basename(static::class);


        $base = Str::replaceLast('Resource', '', $base);

        return $base;
    }


    public static function permissionName(string $ability): string {
        return static::permissionPrefix() . ':' . $ability;
    }

    protected static function user() {
        // Use Filament's own auth to get the currently authenticated panel user.
        // This works for both Resources and Pages regardless of the guard name.
        try {
            return filament()->auth()->user();
        } catch (\Throwable) {
            return Auth::user();
        }
    }

    /**
     * مدخل موحد للتحقق من أي ability.
     */
    protected static function allowed(string $ability): bool {
        $user = static::user();
        if (! $user) {
            return false;
        }

        // SuperAdmin bypass
        if (method_exists($user, 'hasRole') && $user->hasRole(static::superAdminRole())) {
            return true;
        }

        return $user->can(static::permissionName($ability));
        // return $user->hasPermissionTo(static::permissionName($ability), static::filamentGuard());
    }

    public static function canAccess(): bool {
        return static::allowed('access');
    }

    public static function canCreate(): bool {
        return static::allowed('create');
    }

    public static function canDeleteAny(): bool {
        return static::allowed('delete');
    }

    public static function canForceDeleteAny(): bool {
        return static::allowed('force_delete');
    }

    public static function canEdit(Model $record): bool {
        return static::allowed('edit');
    }

    public static function canView(Model $record): bool {
        return static::allowed('view');
    }

    public static function canCustom(string $permission): bool {
        $user = static::user();
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole(static::superAdminRole())) {
            return true;
        }

        return $user->can($permission);
    }
}
