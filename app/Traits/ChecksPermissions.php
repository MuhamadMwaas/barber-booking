<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * The permission-resolution core shared by every Filament class that gates
 * itself on Spatie permissions.
 *
 * It is kept separate from {@see NavigationDefaultAccess} because that trait also
 * exposes the static `canCreate()` / `canEdit()` / `canView()` / `canDeleteAny()`
 * wrappers that Filament Resources expect — and a RelationManager already
 * declares those same names as non-static methods, so it can only take this core.
 */
trait ChecksPermissions
{
    protected static function superAdminRole(): string
    {
        return 'SuperAdmin';
    }

    protected static function permissionPrefix(): string
    {
        $base = class_basename(static::class);

        $base = Str::replaceLast('Resource', '', $base);

        return $base;
    }

    public static function permissionName(string $ability): string
    {
        return static::permissionPrefix() . ':' . $ability;
    }

    protected static function user()
    {
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
    protected static function allowed(string $ability): bool
    {
        $user = static::user();
        if (! $user) {
            return false;
        }

        // SuperAdmin bypass
        if (method_exists($user, 'hasRole') && $user->hasRole(static::superAdminRole())) {
            return true;
        }

        return $user->can(static::permissionName($ability));
    }

    /**
     * Check a fully-qualified permission name (not derived from the class).
     */
    public static function canCustom(string $permission): bool
    {
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
