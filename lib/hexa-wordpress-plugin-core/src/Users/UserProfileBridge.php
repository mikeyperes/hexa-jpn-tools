<?php

namespace Hexa\PluginCore\Users;

/**
 * Typed REST access to one WordPress user's author profile, for sites a
 * control system reaches without WP-CLI (an administrator Application Password,
 * or a host plugin's signed bridge that runs the request as its actor):
 *
 *     GET  /wp-json/hexa-plugin-core/v1/users/<id>/profile
 *     POST /wp-json/hexa-plugin-core/v1/users/<id>/profile
 *          {"native":{...},"meta":{...},"fields":{...},"avatar":{"media_id":123}}
 *
 * GET returns the same shape a WP-CLI profile read returns (one user row, all
 * non-secret meta, the legacy avatar URL and the avatar provider). POST writes
 * only profile data: allowed native fields, non-secret meta, Fields/ACF user
 * fields and the avatar. It never changes a role, capability, login, password
 * or login tokens. Host plugins switch it on with {@see UserProfileBridge::register()}.
 *
 * It also shows a locally stored avatar (`simple_local_avatar` or
 * `wp_user_avatar` meta) through `pre_get_avatar_data` when no avatar plugin
 * does, so a profile photo set this way appears on the site.
 */
final class UserProfileBridge {
    public const REST_NAMESPACE = 'hexa-plugin-core/v1';

    /** Native fields a profile write may change. Login, password and roles are never writable. */
    public const NATIVE_FIELDS = [ 'display_name', 'first_name', 'last_name', 'nickname', 'user_url', 'description', 'user_email' ];

    /** Meta never read or written: login sessions and API credentials. */
    public const PROTECTED_META = [ 'session_tokens', '_application_passwords' ];

    private const AVATAR_PLUGINS = [ 'simple-local-avatars/simple-local-avatars.php', 'one-user-avatar/one-user-avatar.php', 'wp-user-avatar/wp-user-avatar.php' ];

    private static bool $registered = false;

    private function __construct() {}

    public static function register(): void {
        if ( self::$registered || ! function_exists( 'add_action' ) ) {
            return;
        }
        self::$registered = true;
        add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
        add_filter( 'pre_get_avatar_data', [ self::class, 'avatar_data' ], 20, 2 );
    }

    public static function register_routes(): void {
        register_rest_route(
            self::REST_NAMESPACE,
            '/users/(?P<id>\d+)/profile',
            [
                [ 'methods' => 'GET', 'callback' => [ self::class, 'read_route' ], 'permission_callback' => [ self::class, 'can_manage' ] ],
                [ 'methods' => 'POST', 'callback' => [ self::class, 'write_route' ], 'permission_callback' => [ self::class, 'can_manage' ] ],
            ]
        );
    }

    public static function can_manage( \WP_REST_Request $request ): bool {
        $user_id = absint( $request['id'] );

        return $user_id > 0 && current_user_can( 'list_users' ) && current_user_can( 'edit_user', $user_id );
    }

    /** @return array<string,mixed>|\WP_Error */
    public static function read_route( \WP_REST_Request $request ) {
        return self::read( absint( $request['id'] ) );
    }

    /** @return array<string,mixed>|\WP_Error */
    public static function write_route( \WP_REST_Request $request ) {
        $body = $request->get_json_params();
        $body = is_array( $body ) ? $body : $request->get_body_params();

        return self::write( absint( $request['id'] ), is_array( $body ) ? $body : [] );
    }

    /** @return array<string,mixed>|\WP_Error */
    public static function read( int $user_id ) {
        $user = get_userdata( $user_id );
        if ( ! $user instanceof \WP_User ) {
            return new \WP_Error( 'hexa_user_profile_not_found', 'WordPress user #' . $user_id . ' was not found.', [ 'status' => 404 ] );
        }

        $meta = [];
        foreach ( (array) get_user_meta( $user_id ) as $key => $values ) {
            if ( self::protected_meta( (string) $key ) ) {
                continue;
            }
            foreach ( (array) $values as $value ) {
                $meta[] = [ 'meta_key' => (string) $key, 'meta_value' => is_scalar( $value ) ? (string) $value : maybe_serialize( $value ) ];
            }
        }

        $legacy_id = (int) get_user_meta( $user_id, 'wp_user_avatar', true );
        $legacy_url = $legacy_id > 0 ? (string) wp_get_attachment_url( $legacy_id ) : '';

        return [
            'rows'              => [ self::row( $user ) ],
            'meta'              => $meta,
            'legacy_avatar_url' => $legacy_url,
            'avatar_provider'   => self::provider(),
        ];
    }

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed>|\WP_Error
     */
    public static function write( int $user_id, array $body ) {
        if ( ! get_userdata( $user_id ) instanceof \WP_User ) {
            return new \WP_Error( 'hexa_user_profile_not_found', 'WordPress user #' . $user_id . ' was not found.', [ 'status' => 404 ] );
        }
        $written = [];

        $native = array_intersect_key( (array) ( $body['native'] ?? [] ), array_flip( self::NATIVE_FIELDS ) );
        if ( [] !== $native ) {
            $update = [ 'ID' => $user_id ];
            foreach ( $native as $field => $value ) {
                $update[ $field ] = 'user_email' === $field ? sanitize_email( (string) $value ) : (string) $value;
            }
            $result = wp_update_user( $update );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
            $written = array_merge( $written, array_map( static fn( string $f ): string => 'native:' . $f, array_keys( $native ) ) );
        }

        foreach ( (array) ( $body['meta'] ?? [] ) as $key => $value ) {
            $key = (string) $key;
            if ( '' === $key || self::protected_meta( $key ) || self::capability_meta( $key ) ) {
                return new \WP_Error( 'hexa_user_profile_meta_forbidden', 'The meta key ' . $key . ' cannot be written through the profile bridge.', [ 'status' => 403 ] );
            }
            null === $value ? delete_user_meta( $user_id, $key ) : update_user_meta( $user_id, $key, $value );
            $written[] = 'meta:' . $key;
        }

        foreach ( (array) ( $body['fields'] ?? [] ) as $name => $value ) {
            $name = (string) $name;
            if ( '' === $name || self::protected_meta( $name ) || self::capability_meta( $name ) ) {
                return new \WP_Error( 'hexa_user_profile_meta_forbidden', 'The field ' . $name . ' cannot be written through the profile bridge.', [ 'status' => 403 ] );
            }
            $saved = class_exists( \Hexa\PluginCore\Fields\Field::class ) && \Hexa\PluginCore\Fields\Field::available()
                ? \Hexa\PluginCore\Fields\Field::update( $name, $value, 'user_' . $user_id )
                : update_user_meta( $user_id, $name, $value );
            if ( false === $saved && get_user_meta( $user_id, $name, true ) != $value ) { // phpcs:ignore Universal.Operators.StrictComparisons
                return new \WP_Error( 'hexa_user_profile_field_failed', 'The field ' . $name . ' was not saved.', [ 'status' => 500 ] );
            }
            $written[] = 'field:' . $name;
        }

        if ( isset( $body['avatar'] ) && is_array( $body['avatar'] ) ) {
            $avatar = self::set_avatar( $user_id, absint( $body['avatar']['media_id'] ?? 0 ) );
            if ( is_wp_error( $avatar ) ) {
                return $avatar;
            }
            $written[] = 'avatar';
        }

        clean_user_cache( $user_id );
        $profile = self::read( $user_id );

        return is_wp_error( $profile ) ? $profile : $profile + [ 'written' => $written ];
    }

    /** Store (or, with 0, clear) the user's avatar the way the active avatar provider reads it. */
    public static function set_avatar( int $user_id, int $media_id ) {
        if ( $media_id > 0 ) {
            if ( ! wp_attachment_is_image( $media_id ) ) {
                return new \WP_Error( 'hexa_user_profile_avatar_invalid', 'WordPress media #' . $media_id . ' is not an image attachment.', [ 'status' => 422 ] );
            }
            $full = (string) wp_get_attachment_url( $media_id );
            update_option( 'show_avatars', '1' );
            if ( 'simple_local_avatars' === self::provider() ) {
                update_user_meta( $user_id, 'simple_local_avatar', [ 'media_id' => $media_id, 'full' => $full, 'blog_id' => get_current_blog_id() ] );
                update_user_meta( $user_id, 'simple_local_avatar_rating', 'G' );
                do_action( 'simple_local_avatar_updated', $user_id );
            } else {
                $payload = [ 'media_id' => $media_id, 'site_id' => get_current_blog_id(), 'full' => $full ];
                foreach ( [ 24, 48, 96, 250, 256, 500 ] as $size ) {
                    $source = wp_get_attachment_image_src( $media_id, [ $size, $size ] );
                    $payload[ $size ] = is_array( $source ) && ! empty( $source[0] ) ? (string) $source[0] : $full;
                }
                update_user_meta( $user_id, 'wp_user_avatar', $media_id );
                update_user_meta( $user_id, 'wp_user_avatars', $payload );
                update_user_meta( $user_id, 'wp_user_avatars_rating', 'G' );
            }

            return true;
        }
        foreach ( [ 'simple_local_avatar', 'simple_local_avatar_rating', 'wp_user_avatar', 'wp_user_avatars', 'wp_user_avatars_rating' ] as $key ) {
            delete_user_meta( $user_id, $key );
        }

        return true;
    }

    public static function provider(): string {
        $active = (array) get_option( 'active_plugins', [] );
        $network = function_exists( 'get_site_option' ) ? (array) get_site_option( 'active_sitewide_plugins', [] ) : [];
        $slug = 'simple-local-avatars/simple-local-avatars.php';

        return class_exists( 'Simple_Local_Avatars' ) || in_array( $slug, $active, true ) || isset( $network[ $slug ] )
            ? 'simple_local_avatars'
            : 'legacy_avatar_meta';
    }

    /**
     * Serve a locally stored avatar when no avatar plugin is active.
     *
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    public static function avatar_data( $args, $id_or_email ) {
        if ( ! is_array( $args ) || ! empty( $args['url'] ) || self::avatar_plugin_active() ) {
            return $args;
        }
        $user_id = self::avatar_user_id( $id_or_email );
        if ( $user_id <= 0 ) {
            return $args;
        }
        $media_id = (int) get_user_meta( $user_id, 'wp_user_avatar', true );
        if ( $media_id <= 0 ) {
            $simple = get_user_meta( $user_id, 'simple_local_avatar', true );
            $media_id = is_array( $simple ) ? (int) ( $simple['media_id'] ?? 0 ) : 0;
        }
        if ( $media_id <= 0 ) {
            return $args;
        }
        $size = max( 1, (int) ( $args['size'] ?? 96 ) );
        $source = wp_get_attachment_image_src( $media_id, [ $size, $size ] );
        $url = is_array( $source ) && ! empty( $source[0] ) ? (string) $source[0] : (string) wp_get_attachment_url( $media_id );
        if ( '' !== $url ) {
            $args['url'] = $url;
            $args['found_avatar'] = true;
        }

        return $args;
    }

    private static function avatar_plugin_active(): bool {
        if ( class_exists( 'Simple_Local_Avatars' ) || class_exists( 'WP_User_Avatar' ) || defined( 'ONE_USER_AVATAR_VERSION' ) ) {
            return true;
        }
        $active = (array) get_option( 'active_plugins', [] );

        return [] !== array_intersect( self::AVATAR_PLUGINS, $active );
    }

    /** @param mixed $id_or_email */
    private static function avatar_user_id( $id_or_email ): int {
        if ( is_numeric( $id_or_email ) ) {
            return (int) $id_or_email;
        }
        if ( $id_or_email instanceof \WP_User ) {
            return (int) $id_or_email->ID;
        }
        if ( $id_or_email instanceof \WP_Post ) {
            return (int) $id_or_email->post_author;
        }
        if ( $id_or_email instanceof \WP_Comment ) {
            return (int) $id_or_email->user_id;
        }
        if ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
            $user = get_user_by( 'email', $id_or_email );

            return $user instanceof \WP_User ? (int) $user->ID : 0;
        }

        return 0;
    }

    /** @return array<string,mixed> */
    private static function row( \WP_User $user ): array {
        $simple = get_user_meta( $user->ID, 'simple_local_avatar', true );
        $legacy = get_user_meta( $user->ID, 'wp_user_avatars', true );
        $avatar_id = is_array( $simple ) && ! empty( $simple['media_id'] ) ? (int) $simple['media_id'] : (int) get_user_meta( $user->ID, 'wp_user_avatar', true );
        $full = $avatar_id > 0 ? (string) wp_get_attachment_url( $avatar_id ) : '';
        $medium = $avatar_id > 0 ? (string) wp_get_attachment_image_url( $avatar_id, 'medium' ) : '';
        $admin = (string) get_edit_user_link( $user->ID );

        return [
            'id'                  => (int) $user->ID,
            'ID'                  => (int) $user->ID,
            'user_login'          => (string) $user->user_login,
            'user_nicename'       => (string) $user->user_nicename,
            'display_name'        => (string) $user->display_name,
            'user_email'          => (string) $user->user_email,
            'user_url'            => (string) $user->user_url,
            'roles'               => array_values( array_map( 'strval', (array) $user->roles ) ),
            'wp_user_avatar'      => $avatar_id > 0 ? (string) $avatar_id : '',
            'avatar_media_id'     => $avatar_id > 0 ? (string) $avatar_id : '',
            'wp_user_avatars'     => is_scalar( $legacy ) ? (string) $legacy : maybe_serialize( $legacy ),
            'simple_local_avatar' => is_scalar( $simple ) ? (string) $simple : maybe_serialize( $simple ),
            'avatar_url'          => '' !== $medium ? $medium : $full,
            'avatar_thumbnail_url'=> '' !== $medium ? $medium : $full,
            'avatar_full_url'     => $full,
            'author_url'          => (string) get_author_posts_url( $user->ID, (string) $user->user_nicename ),
            'wp_admin_url'        => '' !== $admin ? $admin : (string) admin_url( 'user-edit.php?user_id=' . (int) $user->ID ),
            'post_count'          => (int) count_user_posts( (int) $user->ID, 'post', false ),
            'post_count_known'    => true,
        ];
    }

    private static function protected_meta( string $key ): bool {
        return in_array( $key, self::PROTECTED_META, true );
    }

    /** Role and capability storage (`<prefix>capabilities`, `<prefix>user_level`) is never written here. */
    private static function capability_meta( string $key ): bool {
        return 1 === preg_match( '/(capabilities|user_level)$/', $key );
    }
}
