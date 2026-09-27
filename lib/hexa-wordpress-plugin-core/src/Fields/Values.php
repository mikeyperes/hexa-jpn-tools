<?php

namespace Hexa\PluginCore\Fields;

/**
 * Native load, format and write of field values using ACF's storage layout
 * and return formats. Only used while ACF is not active.
 */
final class Values {
    private const LAYOUT_TYPES = [ 'accordion', 'tab', 'message' ];
    /** ACF field-type `default_value` defaults, merged into every field ACF loads. */
    private const TYPE_DEFAULTS = [
        'text' => '', 'textarea' => '', 'number' => '', 'range' => '', 'email' => '', 'url' => '', 'wysiwyg' => '', 'color_picker' => '',
        'select' => '', 'checkbox' => '', 'radio' => '', 'button_group' => '', 'true_false' => 0,
    ];

    /**
     * Resolve a selector (name or key) to its definition and stored name, as
     * acf_maybe_get_field() does: a key resolves directly; a name resolves
     * through its stored `_name` reference. Reads are strict (a never-saved
     * name is unknown, so get_field() returns the raw stored value or null);
     * update_field() is not strict and also matches registered names.
     *
     * @param array{0:string,1:int|string} $context
     * @return array{0:array<string,mixed>|null,1:string}
     */
    public static function resolve( string $selector, array $context, bool $strict = true ): array {
        if ( str_starts_with( $selector, 'field_' ) ) {
            $field = FieldGroups::get_field( $selector );
            if ( null !== $field ) {
                return [ $field, (string) $field['name'] ];
            }
        }
        // As acf_get_meta_field(): the stored reference is a field key or, occasionally, a field name.
        $reference = Storage::get( $context, $selector, true );
        if ( is_string( $reference ) && '' !== $reference ) {
            $field = FieldGroups::get_field( $reference );
            if ( null !== $field ) {
                return [ $field, $selector ];
            }
        }
        if ( ! $strict ) {
            $field = FieldGroups::get_field( $selector, Storage::acf_id( $context ) );
            if ( null !== $field ) {
                return [ $field, $selector ];
            }
        }
        return [ null, $selector ];
    }

    /**
     * Stored value with repeater/group rows assembled and keyed by sub-field key,
     * as ACF returns it unformatted.
     *
     * @param array{0:string,1:int|string} $context
     * @param array<string,mixed> $field
     */
    public static function load( array $context, array $field, string $name ): mixed {
        $type = (string) $field['type'];
        if ( in_array( $type, self::LAYOUT_TYPES, true ) ) {
            return null;
        }
        if ( 'repeater' === $type ) {
            $count = (int) Storage::get( $context, $name );
            $value = [];
            for ( $i = 0; $i < $count; $i++ ) {
                $row = [];
                foreach ( (array) ( $field['sub_fields'] ?? [] ) as $sub ) {
                    $row[ $sub['key'] ] = self::load( $context, $sub, $name . '_' . $i . '_' . $sub['name'] );
                }
                $value[] = $row;
            }
        } elseif ( 'flexible_content' === $type ) {
            $value = [];
            foreach ( array_values( (array) Storage::get( $context, $name ) ) as $i => $layout_name ) {
                $row = [ 'acf_fc_layout' => (string) $layout_name ];
                foreach ( self::layout_fields( $field, (string) $layout_name ) as $sub ) {
                    $row[ $sub['key'] ] = self::load( $context, $sub, $name . '_' . $i . '_' . $sub['name'] );
                }
                $value[] = $row;
            }
        } elseif ( 'group' === $type ) {
            $value = [];
            foreach ( (array) ( $field['sub_fields'] ?? [] ) as $sub ) {
                $value[ $sub['key'] ] = self::load( $context, $sub, $name . '_' . $sub['name'] );
            }
        } else {
            $value = Storage::get( $context, $name );
            if ( null === $value ) {
                // As acf_get_value(): a missing value takes the field's (or its type's) default.
                $value = $field['default_value'] ?? self::TYPE_DEFAULTS[ $type ] ?? null;
            }
        }
        return Hooks::field_filter( 'load_value', $value, $field, Storage::acf_id( $context ), $field );
    }

    /**
     * Apply the ACF return format.
     *
     * @param array{0:string,1:int|string} $context
     * @param array<string,mixed> $field
     */
    public static function format( mixed $value, array $context, array $field ): mixed {
        $formatted = self::format_type( $value, $context, $field );
        return Hooks::field_filter( 'format_value', $formatted, $field, Storage::acf_id( $context ), $field );
    }

    /**
     * @param array{0:string,1:int|string} $context
     * @param array<string,mixed> $field
     */
    private static function format_type( mixed $value, array $context, array $field ): mixed {
        $return = (string) ( $field['return_format'] ?? '' );
        switch ( (string) $field['type'] ) {
            case 'repeater':
                if ( ! is_array( $value ) || [] === $value ) {
                    return false;
                }
                $rows = [];
                foreach ( $value as $row ) {
                    $out = [];
                    foreach ( (array) ( $field['sub_fields'] ?? [] ) as $sub ) {
                        $out[ $sub['name'] ] = self::format( $row[ $sub['key'] ] ?? null, $context, $sub );
                    }
                    $rows[] = $out;
                }
                return $rows;
            case 'flexible_content':
                if ( ! is_array( $value ) || [] === $value ) {
                    return false;
                }
                $rows = [];
                foreach ( $value as $row ) {
                    $layout = (string) ( $row['acf_fc_layout'] ?? '' );
                    $out = [ 'acf_fc_layout' => $layout ];
                    foreach ( self::layout_fields( $field, $layout ) as $sub ) {
                        $out[ $sub['name'] ] = self::format( $row[ $sub['key'] ] ?? null, $context, $sub );
                    }
                    $rows[] = $out;
                }
                return $rows;
            case 'group':
                $out = [];
                foreach ( (array) ( $field['sub_fields'] ?? [] ) as $sub ) {
                    $out[ $sub['name'] ] = self::format( is_array( $value ) ? ( $value[ $sub['key'] ] ?? null ) : null, $context, $sub );
                }
                return $out;
            case 'true_false':
                return (bool) (int) $value;
            case 'textarea':
                $value = (string) $value;
                $lines = (string) ( $field['new_lines'] ?? '' );
                return 'wpautop' === $lines ? wpautop( $value ) : ( 'br' === $lines ? nl2br( $value ) : $value );
            case 'wysiwyg':
                return self::content( (string) $value );
            case 'select':
            case 'checkbox':
            case 'radio':
            case 'button_group':
                return self::choice( $value, $field, '' !== $return ? $return : 'value' );
            case 'image':
            case 'file':
                return self::media( $value, '' !== $return ? $return : 'array' );
            case 'gallery':
                if ( empty( $value ) ) {
                    return false;
                }
                return array_values( array_filter( array_map( static fn( $id ) => self::media( $id, '' !== $return ? $return : 'array' ), (array) $value ) ) );
            case 'post_object':
            case 'relationship':
                return self::posts( $value, $field, '' !== $return ? $return : 'object' );
            case 'page_link':
                return self::links( $value, $field );
            case 'user':
                return self::users( $value, $field, '' !== $return ? $return : 'array' );
            case 'taxonomy':
                return self::terms( $value, $field, '' !== $return ? $return : 'id' );
            case 'link':
                if ( ! is_array( $value ) ) {
                    return 'url' === $return ? (string) $value : $value;
                }
                $link = array_merge( [ 'title' => '', 'url' => '', 'target' => '' ], $value );
                return 'url' === $return ? $link['url'] : $link;
            case 'date_picker':
                return self::date( $value, '' !== $return ? $return : 'd/m/Y' );
            case 'date_time_picker':
                return self::date( $value, '' !== $return ? $return : 'd/m/Y g:i a' );
            case 'time_picker':
                return self::date( $value, '' !== $return ? $return : 'g:i a' );
            case 'oembed':
                return self::embed( (string) $value );
            default:
                return $value;
        }
    }

    /**
     * Write a value in ACF's layout, firing native update_value filters.
     *
     * @param array{0:string,1:int|string} $context
     * @param array<string,mixed> $field
     */
    public static function write( array $context, array $field, string $name, mixed $value ): bool {
        $value = Hooks::field_filter( 'update_value', $value, $field, Storage::acf_id( $context ), $field, $value );
        if ( null === $value && in_array( $field['type'], [ 'repeater', 'group', 'flexible_content' ], true ) ) {
            return false;
        }
        switch ( (string) $field['type'] ) {
            case 'repeater':
                $rows = is_array( $value ) ? array_values( $value ) : [];
                $old = (int) Storage::get( $context, $name );
                foreach ( $rows as $i => $row ) {
                    foreach ( (array) ( $field['sub_fields'] ?? [] ) as $sub ) {
                        self::write( $context, $sub, $name . '_' . $i . '_' . $sub['name'], self::pick( $row, $sub ) );
                    }
                }
                for ( $i = count( $rows ); $i < $old; $i++ ) {
                    foreach ( (array) ( $field['sub_fields'] ?? [] ) as $sub ) {
                        self::erase( $context, $sub, $name . '_' . $i . '_' . $sub['name'] );
                    }
                }
                // ACF stores an empty repeater as an empty string.
                Storage::update( $context, $name, [] === $rows ? '' : count( $rows ) );
                break;
            case 'flexible_content':
                $rows = is_array( $value ) ? array_values( $value ) : [];
                $layouts = [];
                foreach ( $rows as $i => $row ) {
                    $layout = (string) ( is_array( $row ) ? ( $row['acf_fc_layout'] ?? '' ) : '' );
                    $layouts[] = $layout;
                    foreach ( self::layout_fields( $field, $layout ) as $sub ) {
                        self::write( $context, $sub, $name . '_' . $i . '_' . $sub['name'], self::pick( $row, $sub ) );
                    }
                }
                Storage::update( $context, $name, $layouts );
                break;
            case 'group':
                foreach ( (array) ( $field['sub_fields'] ?? [] ) as $sub ) {
                    self::write( $context, $sub, $name . '_' . $sub['name'], self::pick( $value, $sub ) );
                }
                Storage::update( $context, $name, '' );
                break;
            default:
                if ( in_array( $field['type'], self::LAYOUT_TYPES, true ) ) {
                    return true;
                }
                Storage::update( $context, $name, self::storable( $field, $value ) );
        }
        if ( '' !== (string) $field['key'] ) {
            Storage::update( $context, $name, (string) $field['key'], true );
        }
        return true;
    }

    /**
     * @param array{0:string,1:int|string} $context
     * @param array<string,mixed> $field
     */
    public static function erase( array $context, array $field, string $name ): bool {
        if ( 'repeater' === $field['type'] || 'flexible_content' === $field['type'] ) {
            $count = 'repeater' === $field['type'] ? (int) Storage::get( $context, $name ) : count( (array) Storage::get( $context, $name ) );
            for ( $i = 0; $i < $count; $i++ ) {
                foreach ( (array) ( $field['sub_fields'] ?? [] ) as $sub ) {
                    self::erase( $context, $sub, $name . '_' . $i . '_' . $sub['name'] );
                }
            }
        } elseif ( 'group' === $field['type'] ) {
            foreach ( (array) ( $field['sub_fields'] ?? [] ) as $sub ) {
                self::erase( $context, $sub, $name . '_' . $sub['name'] );
            }
        }
        Storage::delete( $context, $name, true );
        return Storage::delete( $context, $name );
    }

    /** @param array<string,mixed> $field */
    private static function storable( array $field, mixed $value ): mixed {
        $multiple = ! empty( $field['multiple'] ) || in_array( $field['type'], [ 'gallery', 'relationship', 'checkbox' ], true )
            || ( 'taxonomy' === $field['type'] && in_array( (string) ( $field['field_type'] ?? 'checkbox' ), [ 'checkbox', 'multi_select' ], true ) );
        switch ( (string) $field['type'] ) {
            case 'true_false':
                return ! empty( $value ) && '0' !== $value ? 1 : 0;
            case 'image':
            case 'file':
            case 'post_object':
            case 'relationship':
            case 'gallery':
            case 'user':
            case 'taxonomy':
            case 'page_link':
                $ids = array_values( array_filter( array_map( [ self::class, 'object_id' ], is_array( $value ) && ( $multiple || array_is_list( $value ) ) ? $value : [ $value ] ), static fn( $id ) => '' !== $id && null !== $id ) );
                return $multiple ? ( [] === $ids ? '' : array_map( 'strval', $ids ) ) : ( $ids[0] ?? '' );
            case 'select':
                return $multiple ? ( empty( $value ) ? '' : array_values( (array) $value ) ) : ( is_array( $value ) ? (string) reset( $value ) : $value );
            case 'checkbox':
                return empty( $value ) ? '' : array_values( (array) $value );
            case 'date_picker':
                $value = (string) $value;
                return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? str_replace( '-', '', $value ) : $value;
            case 'date_time_picker':
                $value = (string) $value;
                return preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $value ) ? str_replace( 'T', ' ', $value ) . ( 16 === strlen( $value ) ? ':00' : '' ) : $value;
            case 'link':
                return is_array( $value ) ? array_intersect_key( array_merge( [ 'title' => '', 'url' => '', 'target' => '' ], $value ), [ 'title' => 1, 'url' => 1, 'target' => 1 ] ) : $value;
            default:
                return $value;
        }
    }

    private static function object_id( mixed $item ): int|string {
        if ( $item instanceof \WP_Post || $item instanceof \WP_User ) {
            return (int) $item->ID;
        }
        if ( $item instanceof \WP_Term ) {
            return (int) $item->term_id;
        }
        if ( is_array( $item ) ) {
            return (int) ( $item['ID'] ?? $item['id'] ?? $item['term_id'] ?? 0 ) ?: '';
        }
        return is_numeric( $item ) ? (int) $item : ( is_string( $item ) ? $item : '' );
    }

    /** @param array<string,mixed> $sub */
    private static function pick( mixed $row, array $sub ): mixed {
        if ( ! is_array( $row ) ) {
            return null;
        }
        return array_key_exists( $sub['name'], $row ) ? $row[ $sub['name'] ] : ( $row[ $sub['key'] ] ?? null );
    }

    /**
     * @param array<string,mixed> $field
     * @return array<int,array<string,mixed>>
     */
    private static function layout_fields( array $field, string $layout ): array {
        foreach ( (array) ( $field['layouts'] ?? [] ) as $definition ) {
            if ( is_array( $definition ) && $layout === (string) ( $definition['name'] ?? '' ) ) {
                return (array) ( $definition['sub_fields'] ?? [] );
            }
        }
        return [];
    }

    /** ACF's `acf_the_content` chain, registered once when ACF is not active so site hooks on it keep working. */
    private static function content( string $value ): string {
        if ( '' === $value ) {
            return $value;
        }
        if ( ! has_filter( 'acf_the_content', 'wpautop' ) ) {
            add_filter( 'acf_the_content', 'capital_P_dangit', 11 );
            add_filter( 'acf_the_content', 'wptexturize' );
            add_filter( 'acf_the_content', 'convert_smilies', 20 );
            add_filter( 'acf_the_content', 'wpautop' );
            add_filter( 'acf_the_content', 'shortcode_unautop' );
            add_filter( 'acf_the_content', function_exists( 'wp_filter_content_tags' ) ? 'wp_filter_content_tags' : 'wp_make_content_images_responsive' );
            add_filter( 'acf_the_content', 'do_shortcode', 11 );
        }
        return (string) apply_filters( 'acf_the_content', $value );
    }

    /** @param array<string,mixed> $field */
    private static function choice( mixed $value, array $field, string $return ): mixed {
        $choices = (array) ( $field['choices'] ?? [] );
        $map = static function ( $item ) use ( $choices, $return ) {
            $label = $choices[ $item ] ?? $item;
            return 'label' === $return ? $label : ( 'array' === $return ? [ 'value' => $item, 'label' => $label ] : $item );
        };
        if ( is_array( $value ) ) {
            return array_map( $map, $value );
        }
        if ( null === $value || '' === $value ) {
            return $value;
        }
        return $map( $value );
    }

    private static function media( mixed $value, string $return ): mixed {
        $id = (int) self::object_id( $value );
        if ( $id <= 0 ) {
            return false;
        }
        if ( 'id' === $return ) {
            return $id;
        }
        if ( 'url' === $return ) {
            return (string) wp_get_attachment_url( $id );
        }
        return self::attachment( $id );
    }

    /** @return array<string,mixed>|false ACF-shaped attachment array. */
    public static function attachment( int $id ): array|false {
        $post = get_post( $id );
        if ( ! $post instanceof \WP_Post || 'attachment' !== $post->post_type ) {
            return false;
        }
        $url = (string) wp_get_attachment_url( $id );
        $meta = (array) wp_get_attachment_metadata( $id );
        $mime = explode( '/', (string) $post->post_mime_type, 2 );
        $file = get_attached_file( $id );
        $attachment = [
            'ID' => $id, 'id' => $id, 'title' => $post->post_title, 'filename' => wp_basename( $file ?: $url ),
            'filesize' => (int) ( $meta['filesize'] ?? ( $file && file_exists( $file ) ? filesize( $file ) : 0 ) ),
            'url' => $url, 'link' => get_attachment_link( $id ), 'alt' => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
            'author' => (string) $post->post_author, 'description' => $post->post_content, 'caption' => $post->post_excerpt,
            'name' => $post->post_name, 'status' => $post->post_status, 'uploaded_to' => (int) $post->post_parent,
            'date' => $post->post_date_gmt, 'modified' => $post->post_modified_gmt, 'menu_order' => (int) $post->menu_order,
            'mime_type' => $post->post_mime_type, 'type' => $mime[0], 'subtype' => $mime[1] ?? '', 'icon' => wp_mime_type_icon( $id ),
        ];
        if ( 'image' === $mime[0] ) {
            $attachment['width'] = (int) ( $meta['width'] ?? 0 );
            $attachment['height'] = (int) ( $meta['height'] ?? 0 );
            $attachment['sizes'] = [];
            foreach ( get_intermediate_image_sizes() as $size ) {
                $source = wp_get_attachment_image_src( $id, $size );
                if ( is_array( $source ) ) {
                    $attachment['sizes'][ $size ] = $source[0];
                    $attachment['sizes'][ $size . '-width' ] = (int) $source[1];
                    $attachment['sizes'][ $size . '-height' ] = (int) $source[2];
                }
            }
        }
        return $attachment;
    }

    /** @param array<string,mixed> $field */
    private static function posts( mixed $value, array $field, string $return ): mixed {
        $multiple = 'relationship' === $field['type'] || ! empty( $field['multiple'] );
        $ids = array_values( array_filter( array_map( 'intval', is_array( $value ) ? $value : ( '' === (string) $value || null === $value ? [] : [ $value ] ) ) ) );
        if ( [] === $ids ) {
            return false;
        }
        $items = 'id' === $return ? $ids : self::visible_posts( $ids, $field );
        return $multiple ? $items : ( $items[0] ?? false );
    }

    /**
     * Posts acf_get_posts() returns for these IDs: registered post types (the
     * field's own when set), not trashed or auto-draft, in ID order.
     *
     * @param array<int,int> $ids
     * @param array<string,mixed> $field
     * @return array<int,\WP_Post>
     */
    private static function visible_posts( array $ids, array $field ): array {
        $types = array_filter( (array) ( $field['post_type'] ?? [] ) );
        $statuses = array_filter( (array) ( $field['post_status'] ?? [] ) );
        $posts = [];
        foreach ( $ids as $id ) {
            $post = get_post( (int) $id );
            if ( ! $post instanceof \WP_Post || ! post_type_exists( $post->post_type ) || in_array( $post->post_type, [ 'acf-field-group', 'acf-field' ], true ) ) {
                continue;
            }
            if ( [] !== $types && ! in_array( $post->post_type, $types, true ) ) {
                continue;
            }
            if ( [] !== $statuses ? ! in_array( $post->post_status, $statuses, true ) : in_array( $post->post_status, [ 'trash', 'auto-draft' ], true ) ) {
                continue;
            }
            $posts[] = $post;
        }
        return $posts;
    }

    /** @param array<string,mixed> $field */
    private static function links( mixed $value, array $field ): mixed {
        if ( empty( $value ) ) {
            return $value;
        }
        // As ACF's page_link: post IDs become permalinks of visible posts; archive URLs stay.
        $items = [];
        foreach ( (array) $value as $item ) {
            if ( ! is_numeric( $item ) ) {
                $items[] = $item;
                continue;
            }
            $post = self::visible_posts( [ (int) $item ], $field )[0] ?? null;
            if ( null !== $post ) {
                $items[] = (string) get_permalink( $post );
            }
        }
        return ! empty( $field['multiple'] ) ? $items : ( $items[0] ?? null );
    }

    /** @param array<string,mixed> $field */
    private static function users( mixed $value, array $field, string $return ): mixed {
        $ids = array_values( array_filter( array_map( 'intval', is_array( $value ) ? $value : ( empty( $value ) ? [] : [ $value ] ) ) ) );
        $items = [];
        foreach ( $ids as $id ) {
            if ( 'id' === $return ) {
                $items[] = $id;
                continue;
            }
            $user = get_userdata( $id );
            if ( ! $user instanceof \WP_User ) {
                continue;
            }
            $items[] = 'object' === $return ? $user : [
                'ID' => $user->ID, 'user_firstname' => $user->user_firstname, 'user_lastname' => $user->user_lastname,
                'nickname' => $user->nickname, 'user_nicename' => $user->user_nicename, 'display_name' => $user->display_name,
                'user_email' => $user->user_email, 'user_url' => $user->user_url, 'user_registered' => $user->user_registered,
                'user_description' => $user->user_description, 'user_avatar' => get_avatar( $user->ID ),
            ];
        }
        return ! empty( $field['multiple'] ) ? ( [] === $items ? false : $items ) : ( $items[0] ?? false );
    }

    /** @param array<string,mixed> $field */
    private static function terms( mixed $value, array $field, string $return ): mixed {
        $multiple = in_array( (string) ( $field['field_type'] ?? 'checkbox' ), [ 'checkbox', 'multi_select' ], true );
        $ids = array_values( array_filter( array_map( 'intval', is_array( $value ) ? $value : ( empty( $value ) ? [] : [ $value ] ) ) ) );
        $items = 'object' === $return ? array_values( array_filter( array_map( static fn( int $id ) => ( $term = get_term( $id ) ) instanceof \WP_Term ? $term : null, $ids ) ) ) : $ids;
        return $multiple ? ( [] === $items ? false : $items ) : ( $items[0] ?? false );
    }

    private static function date( mixed $value, string $format ): mixed {
        if ( empty( $value ) || ! is_string( $value ) ) {
            return $value;
        }
        $timestamp = strtotime( preg_match( '/^\d{8}$/', $value ) ? substr( $value, 0, 4 ) . '-' . substr( $value, 4, 2 ) . '-' . substr( $value, 6, 2 ) : $value );
        return false === $timestamp ? $value : date_i18n( $format, $timestamp );
    }

    private static function embed( string $url ): string {
        if ( '' === $url ) {
            return $url;
        }
        $cache = 'hexa_fields_oembed_' . md5( $url );
        $html = get_transient( $cache );
        if ( false === $html ) {
            $html = (string) wp_oembed_get( $url );
            set_transient( $cache, $html, DAY_IN_SECONDS );
        }
        return '' !== $html ? $html : $url;
    }
}
