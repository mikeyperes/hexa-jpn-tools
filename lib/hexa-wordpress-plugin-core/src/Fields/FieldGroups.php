<?php

namespace Hexa\PluginCore\Fields;

/** Registers Core field definitions and resolves legacy stored definitions. */
final class FieldGroups {
    /** @var array<string,array<string,mixed>> */
    private static array $groups = [];
    /** @var array<int,array<string,mixed>> */
    private static array $pending = [];
    private static bool $pending_hooked = false;
    /** @var array<string,array<string,mixed>>|null Normalized database groups. */
    private static ?array $database = null;

    /** @param array<string,mixed> $group */
    public static function add( array $group ): void {
        if ( ! did_action( 'init' ) && ! doing_action( 'init' ) ) {
            // Resolve the current user and audience after WordPress initialization.
            self::$pending[] = $group;
            if ( ! self::$pending_hooked ) {
                self::$pending_hooked = true;
                add_action( 'init', [ self::class, 'flush_pending' ], 0 );
            }
            return;
        }

        $group = self::normalize( $group );
        if ( '' === $group['key'] ) {
            return;
        }
        // Staff-only groups are not registered for client-view users; developer notes
        // (shortcodes, storage details) only reach users outside the client view.
        if ( Audience::hidden( $group ) ) {
            return;
        }
        $group = Audience::apply( $group );
        self::$groups[ $group['key'] ] = $group;

        AdminScreens::boot();
    }

    public static function remove( string $key ): bool {
        $existed = isset( self::$groups[ $key ] );
        unset( self::$groups[ $key ] );
        return $existed;
    }

    /** Runs a field registration callback during Core initialization. */
    public static function ready( callable $callback, int $priority = 10 ): void {
        Hooks::on( 'init', $callback, $priority );
    }

    public static function flush_pending(): void {
        $pending = self::$pending;
        self::$pending = [];
        foreach ( $pending as $group ) {
            self::add( $group );
        }
    }

    /** @return array<string,mixed>|null */
    public static function get_group( string $key ): ?array {
        if ( '' === $key ) {
            return null;
        }
        return self::$groups[ $key ] ?? self::database()[ $key ] ?? null;
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(): array {
        return self::listed();
    }

    /**
     * Code and database groups after the `load_field_groups` filter, as
     * acf_get_field_groups() returns them.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function listed(): array {
        return array_values( array_filter( (array) apply_filters( 'hexa_fields/load_field_groups', array_values( self::$groups + self::database() ) ), 'is_array' ) );
    }

    /**
     * Groups created in the ACF admin screen (see Database), normalized, minus
     * any key registered in code.
     *
     * @return array<string,array<string,mixed>>
     */
    private static function database( bool $including_overridden = false ): array {
        if ( null === self::$database ) {
            self::$database = array_map( [ self::class, 'normalize' ], Database::groups() );
        }
        // ACF lists a code group in place of a database group with the same key and never
        // lists a trashed group, but still finds the fields of both.
        return $including_overridden ? self::$database : array_filter( array_diff_key( self::$database, self::$groups ), static fn( array $group ): bool => empty( $group['trashed'] ) );
    }

    /** @return array<int,array<string,mixed>> Native groups only. */
    public static function native(): array {
        return array_values( self::$groups );
    }

    /**
     * Fields of one group, or the group containing a field key.
     *
     * @param array<string,mixed>|string $group
     * @return array<int,array<string,mixed>>
     */
    public static function fields( array|string $group ): array {
        $group = is_array( $group ) ? $group : self::get_group( $group );
        return is_array( $group ) ? (array) $group['fields'] : [];
    }

    /**
     * One field definition by key or name, preferring groups that apply to
     * the given context.
     *
     * @return array<string,mixed>|null
     */
    public static function get_field( string $selector, mixed $context = false ): ?array {
        if ( '' === $selector ) {
            return null;
        }

        $is_key = str_starts_with( $selector, 'field_' );
        $screen = false === $context ? null : Storage::screen( Storage::context( $context ) );
        // Code-registered groups before database groups; a name matches top-level fields
        // first and then, as ACF's field store does, any sub field.
        $passes = $is_key ? [ [ true, false ], [ false, false ] ] : [ [ true, false ], [ false, false ], [ true, true ], [ false, true ] ];
        foreach ( $passes as [ $in_code, $deep ] ) {
            $fallback = null;
            foreach ( $in_code ? self::$groups : self::database( true ) as $group ) {
                $applies = null === $screen || self::matches( $group, $screen );
                $found = $is_key ? self::find_key( (array) $group['fields'], $selector, (string) $group['key'] ) : self::find_name( (array) $group['fields'], $selector, (string) $group['key'], $deep );
                if ( null === $found ) {
                    continue;
                }
                if ( $applies ) {
                    return $found;
                }
                $fallback ??= $found;
            }
            if ( null !== $fallback ) {
                return $fallback;
            }
        }
        return null;
    }

    /**
     * Native groups whose location rules match one admin screen.
     *
     * @param array<string,mixed> $screen
     * @return array<int,array<string,mixed>>
     */
    public static function for_screen( array $screen, bool $core_only = false ): array {
        $groups = array_filter( self::listed(), static fn( array $group ): bool => ! empty( $group['active'] ) && ( ! $core_only || isset( self::$groups[ $group['key'] ] ) ) && self::matches( $group, $screen ) );
        uasort( $groups, static fn( array $a, array $b ): int => (int) $a['menu_order'] <=> (int) $b['menu_order'] );
        return array_values( $groups );
    }

    /**
     * ACF location rules: OR of AND rule sets.
     *
     * @param array<string,mixed> $group
     * @param array<string,mixed> $screen
     */
    public static function matches( array $group, array $screen ): bool {
        foreach ( (array) ( $group['location'] ?? [] ) as $rules ) {
            if ( ! is_array( $rules ) || [] === $rules ) {
                continue;
            }
            $all = true;
            foreach ( $rules as $rule ) {
                if ( ! is_array( $rule ) || ! self::rule( $rule, $screen, $group ) ) {
                    $all = false;
                    break;
                }
            }
            if ( $all ) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string,mixed> $rule
     * @param array<string,mixed> $screen
     * @param array<string,mixed> $group
     */
    private static function rule( array $rule, array $screen, array $group = [] ): bool {
        $param = (string) ( $rule['param'] ?? '' );
        $result = self::builtin_rule( $rule, $screen, $group );
        // As acf_match_location_rule(): every result passes through ACF's location filters,
        // which is also how hosts define their own rules (`location/rule_match/<param>`).
        foreach ( [ 'location/match_rule/type=' . $param, 'location/match_rule', 'location/rule_match/' . $param, 'location/rule_match' ] as $hook ) {
            $result = (bool) apply_filters( 'hexa_fields/' . $hook, $result, $rule, $screen, $group );
        }
        return $result;
    }

    /**
     * @param array<string,mixed> $rule
     * @param array<string,mixed> $screen
     * @param array<string,mixed> $group
     */
    private static function builtin_rule( array $rule, array $screen, array $group = [] ): bool {
        $param = (string) ( $rule['param'] ?? '' );
        $value = (string) ( $rule['value'] ?? '' );
        $equal = '!=' !== (string) ( $rule['operator'] ?? '==' );

        switch ( $param ) {
            case 'post_type':
                $actual = $screen['post_type'] ?? null;
                break;
            case 'post':
            case 'page':
                $actual = isset( $screen['post_id'] ) ? (string) $screen['post_id'] : null;
                break;
            case 'page_template':
            case 'post_template':
                $actual = isset( $screen['post_type'] ) ? (string) ( $screen['template'] ?? 'default' ) : null;
                break;
            case 'post_status':
                $actual = isset( $screen['post_id'] ) ? (string) get_post_status( (int) $screen['post_id'] ) : null;
                break;
            case 'post_taxonomy':
            case 'post_category':
                if ( ! isset( $screen['post_id'] ) ) {
                    return false;
                }
                [ $taxonomy, $term ] = array_pad( explode( ':', 'post_category' === $param ? 'category:' . $value : $value, 2 ), 2, '' );
                $hit = '' !== $term && has_term( $term, $taxonomy, (int) $screen['post_id'] );
                return $equal ? $hit : ! $hit;
            case 'user_form':
                if ( ! isset( $screen['user_form'] ) ) {
                    return false;
                }
                $hit = 'all' === $value || $value === $screen['user_form'];
                return $equal ? $hit : ! $hit;
            case 'user_role':
                if ( ! isset( $screen['user_roles'] ) ) {
                    return false;
                }
                $hit = 'all' === $value || in_array( $value, (array) $screen['user_roles'], true );
                return $equal ? $hit : ! $hit;
            case 'current_user_role':
                $user = wp_get_current_user();
                $hit = 'super_admin' === $value ? is_super_admin() : in_array( $value, (array) $user->roles, true );
                return $equal ? $hit : ! $hit;
            case 'current_user':
                $hit = 'logged_in' === $value ? is_user_logged_in() : ( 'viewing_back' === $value ? is_admin() : ( 'viewing_front' === $value ? ! is_admin() : false ) );
                return $equal ? $hit : ! $hit;
            case 'taxonomy':
                if ( ! isset( $screen['taxonomy'] ) ) {
                    return false;
                }
                $hit = 'all' === $value || $value === $screen['taxonomy'];
                return $equal ? $hit : ! $hit;
            case 'options_page':
                $actual = $screen['options_page'] ?? null;
                if ( '*' === $actual ) {
                    return $equal;
                }
                break;
            default:
                // Unknown to Core: host rules decide through the location filters in rule().
                return false;
        }
        if ( null === $actual ) {
            return false;
        }
        return $equal ? (string) $actual === $value : (string) $actual !== $value;
    }

    /**
     * @param array<string,mixed> $group
     * @return array<string,mixed>
     */
    private static function normalize( array $group ): array {
        $group = array_merge(
            [ 'key' => '', 'title' => '', 'fields' => [], 'location' => [], 'menu_order' => 0, 'position' => 'normal', 'active' => true, 'description' => '' ],
            $group
        );
        $group['key'] = (string) $group['key'];
        if ( '' === $group['key'] && '' !== self::slug( (string) $group['title'] ) ) {
            $group['key'] = 'group_' . self::slug( (string) $group['title'] ); // As acf_add_local_field_group().
        }
        $group['fields'] = self::normalize_fields( (array) $group['fields'], $group['key'] );
        $group['active'] = ! isset( $group['active'] ) || (bool) $group['active'];
        return $group;
    }

    /** acf_slugify( $title, '_' ). */
    private static function slug( string $title ): string {
        $slug = str_replace( [ '-', '/', ' ' ], '_', $title );
        $slug = strtolower( function_exists( 'remove_accents' ) ? remove_accents( $slug ) : $slug );
        return (string) preg_replace( '/[^a-z0-9_]/', '', $slug );
    }

    /**
     * @param array<int,mixed> $fields
     * @return array<int,array<string,mixed>>
     */
    private static function normalize_fields( array $fields, string $parent ): array {
        $normalized = [];
        foreach ( $fields as $field ) {
            if ( ! is_array( $field ) ) {
                continue;
            }
            $field['name'] = (string) ( $field['name'] ?? '' );
            $field['type'] = (string) ( $field['type'] ?? 'text' );
            $field['key'] = (string) ( $field['key'] ?? '' );
            if ( '' === $field['key'] ) {
                // As acf_add_local_field(): `field_<name>`.
                $field['key'] = '' !== $field['name'] ? 'field_' . $field['name'] : 'field_' . substr( md5( $parent . '|' . $field['type'] . '|' . count( $normalized ) ), 0, 13 );
            }
            $field['label'] = (string) ( $field['label'] ?? $field['name'] );
            $field['parent'] = $parent;
            if ( isset( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
                $field['sub_fields'] = self::normalize_fields( $field['sub_fields'], $field['key'] );
            }
            $normalized[] = $field;
        }
        return $normalized;
    }

    /**
     * @param array<int,array<string,mixed>> $fields
     * @return array<string,mixed>|null
     */
    private static function find_key( array $fields, string $key, string $group ): ?array {
        foreach ( $fields as $field ) {
            if ( $key === $field['key'] ) {
                return $field;
            }
            foreach ( self::children( $field ) as $children ) {
                $found = self::find_key( $children, $key, $group );
                if ( null !== $found ) {
                    return $found;
                }
            }
        }
        return null;
    }

    /**
     * Sub-field lists of a field: its sub fields and each flexible-content layout's.
     *
     * @param array<string,mixed> $field
     * @return array<int,array<int,array<string,mixed>>>
     */
    private static function children( array $field ): array {
        $lists = [];
        if ( ! empty( $field['sub_fields'] ) ) {
            $lists[] = (array) $field['sub_fields'];
        }
        foreach ( (array) ( $field['layouts'] ?? [] ) as $layout ) {
            if ( is_array( $layout ) && ! empty( $layout['sub_fields'] ) ) {
                $lists[] = (array) $layout['sub_fields'];
            }
        }
        return $lists;
    }

    /**
     * @param array<int,array<string,mixed>> $fields
     * @return array<string,mixed>|null
     */
    private static function find_name( array $fields, string $name, string $group, bool $deep = false ): ?array {
        foreach ( $fields as $field ) {
            if ( $name === $field['name'] ) {
                return $field;
            }
        }
        if ( $deep ) {
            foreach ( $fields as $field ) {
                foreach ( self::children( $field ) as $children ) {
                    $found = self::find_name( $children, $name, $group, true );
                    if ( null !== $found ) {
                        return $found;
                    }
                }
            }
        }
        return null;
    }
}
