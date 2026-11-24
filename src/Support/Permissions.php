<?php

namespace StevenBrown85\TwillResponseCache\Support;

use Illuminate\Support\Facades\Auth;

class Permissions
{
    public static function currentUserIsAllowed(): bool
    {
        $user = Auth::guard('twill_users')->user();

        if (! $user) {
            return false;
        }

        $allowedRoles = config('twill-responsecache.permissions.allowed_roles');

        if ($allowedRoles === null) {
            return true;
        }

        // With Twill's permissions-management enabled, `role` is a relation
        // rather than a string - superadmins are always allowed through.
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        $role = $user->role;

        if (is_object($role)) {
            $role = $role->name ?? null;
        }

        return in_array($role, $allowedRoles, true);
    }
}
