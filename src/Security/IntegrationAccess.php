<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Security;

final class IntegrationAccess
{
    public const CAPABILITY = 'manage_jpn_integration';
    public const ROLE = 'hexa_jpn_integration';

    public static function canManage(): bool
    {
        return current_user_can(self::CAPABILITY);
    }

    public static function grantToAdministrators(): bool
    {
        $role = get_role('administrator');
        if (!$role) {
            return false;
        }

        $role->add_cap(self::CAPABILITY, true);
        return (bool) $role->has_cap(self::CAPABILITY);
    }

    public static function install(): bool
    {
        $role = get_role(self::ROLE);
        if (!$role) {
            $role = add_role(self::ROLE, __('Hexa JPN Integration', 'hexa-jpn-tools'), [
                'read' => true,
                self::CAPABILITY => true,
            ]);
        }
        if (!$role) {
            return false;
        }

        $role->add_cap('read', true);
        $role->add_cap(self::CAPABILITY, true);

        return $role->has_cap(self::CAPABILITY) && self::grantToAdministrators();
    }
}
