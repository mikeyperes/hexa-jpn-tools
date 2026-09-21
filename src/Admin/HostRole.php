<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Admin;

final class HostRole
{
    public const ROLE = 'host';
    public const DEFAULT_ROLE_OPTION = 'hexa_jpn_default_new_user_role';

    public function register(): void
    {
        add_filter('pre_option_default_role', [$this, 'defaultRole']);
    }

    public function defaultRole(mixed $current): mixed
    {
        $configured = (string) get_option(self::DEFAULT_ROLE_OPTION, self::ROLE);
        if ($configured === self::ROLE && get_role(self::ROLE)) {
            return self::ROLE;
        }

        return $current;
    }
}
