<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;

trait NavigationDefaultAccess {

    // Permission resolution (superAdminRole / permissionPrefix / permissionName /
    // user / allowed / canCustom) lives in ChecksPermissions so classes that
    // cannot take the static can* wrappers below — RelationManagers declare their
    // own non-static ones — can still reuse the same rules.
    use ChecksPermissions;

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
}
