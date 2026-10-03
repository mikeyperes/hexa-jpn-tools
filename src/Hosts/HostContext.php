<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Hosts;

use WP_User;

/** Resolves the host represented by the current author-archive request. */
final class HostContext
{
    public static function currentAuthorId(): int
    {
        $object = get_queried_object();
        if ($object instanceof WP_User) {
            return (int) $object->ID;
        }

        $authorId = (int) get_query_var('author');
        if ($authorId > 0) {
            return $authorId;
        }

        $authorName = sanitize_title((string) get_query_var('author_name'));
        if ($authorName !== '') {
            $user = get_user_by('slug', $authorName);
            if ($user instanceof WP_User) {
                return (int) $user->ID;
            }
        }

        global $authordata;
        return $authordata instanceof WP_User ? (int) $authordata->ID : 0;
    }
}
