<?php

namespace App\Traits;

use Illuminate\Auth\Access\Response;
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

    public static function canDelete(Model $record): bool {
        return static::allowed('delete');
    }

    public static function canDeleteAny(): bool {
        return static::allowed('delete');
    }

    /*
     * Filament 4 does NOT call canDelete()/canDeleteAny() to authorize a
     * DeleteAction / DeleteBulkAction — it calls these two, which default to
     * the model's Policy. There are no Policies in this project, so without
     * these overrides every delete button was allowed for anyone with panel
     * access, whatever the `<Resource>:delete` permission said.
     *
     * They route through canDelete()/canDeleteAny() so a Resource that overrides
     * those (e.g. RoleResource protecting built-in roles) is honoured too.
     */
    public static function getDeleteAuthorizationResponse(Model $record): Response {
        return static::canDelete($record) ? Response::allow() : Response::deny();
    }

    public static function getDeleteAnyAuthorizationResponse(): Response {
        return static::canDeleteAny() ? Response::allow() : Response::deny();
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
