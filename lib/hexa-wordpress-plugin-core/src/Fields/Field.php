<?php

namespace Hexa\PluginCore\Fields;

/**
 * The one field API for Hexa plugins.
 *
 * Signatures match ACF's template functions. With ACF active every call is
 * delegated to ACF unchanged; without ACF the native engine reads and writes
 * the same storage and returns the same formats.
 */
final class Field {
    /** @var array<int,array{rows:array<int,array<string,mixed>>,raw:array<int,array<string,mixed>>,index:int,selector:string,context:string}> */
    private static array $loops = [];

    /** Always true once this Core version is loaded; replaces `function_exists( 'get_field' )` guards. */
    public static function available(): bool {
        return true;
    }

    public static function get( string $selector, mixed $context = false, bool $format = true, bool $escape_html = false ): mixed {
        if ( Acf::active() ) {
            return get_field( $selector, $context, $format, $escape_html );
        }
        if ( '' === $selector ) {
            return null;
        }
        $context = Storage::context( $context );
        [ $field, $name ] = Values::resolve( $selector, $context );
        if ( null === $field ) {
            return Storage::get( $context, $selector );
        }
        $value = Values::load( $context, $field, $name );
        $value = $format ? Values::format( $value, $context, $field ) : $value;
        return $escape_html && is_string( $value ) ? wp_kses_post( $value ) : $value;
    }

    public static function the( string $selector, mixed $context = false, bool $format = true ): void {
        if ( Acf::active() ) {
            the_field( $selector, $context, $format );
            return;
        }
        $value = self::get( $selector, $context, $format );
        echo is_array( $value ) ? esc_html( implode( ', ', array_filter( $value, 'is_scalar' ) ) ) : wp_kses_post( (string) $value ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public static function update( string $selector, mixed $value, mixed $context = false ): bool {
        if ( Acf::active() ) {
            return (bool) update_field( $selector, $value, $context );
        }
        $context = Storage::context( $context );
        [ $field, $name ] = Values::resolve( $selector, $context, false );
        if ( null === $field ) {
            return Storage::update( $context, $selector, $value );
        }
        return Values::write( $context, $field, $name, $value );
    }

    public static function delete( string $selector, mixed $context = false ): bool {
        if ( Acf::active() ) {
            return (bool) delete_field( $selector, $context );
        }
        $context = Storage::context( $context );
        [ $field, $name ] = Values::resolve( $selector, $context );
        // Like delete_field(): a name with no stored reference is not a field.
        return null === $field ? false : Values::erase( $context, $field, $name );
    }

    /** @return array<string,mixed>|false Equivalent of get_fields(). */
    public static function all( mixed $context = false, bool $format = true ): array|false {
        if ( Acf::active() ) {
            return get_fields( $context, $format );
        }
        $values = [];
        foreach ( self::objects( $context, $format ) ?: [] as $name => $object ) {
            $values[ $name ] = $object['value'];
        }
        return [] === $values ? false : $values;
    }

    /** @return array<string,mixed>|false Equivalent of get_field_object(). */
    public static function object( string $selector, mixed $context = false, bool $format = true, bool $load_value = true ): array|false {
        if ( Acf::active() ) {
            return get_field_object( $selector, $context, $format, $load_value );
        }
        $resolved = Storage::context( $context );
        [ $field, $name ] = Values::resolve( $selector, $resolved );
        if ( null === $field ) {
            return false;
        }
        if ( $load_value ) {
            $value = Values::load( $resolved, $field, $name );
            $field['value'] = $format ? Values::format( $value, $resolved, $field ) : $value;
        }
        return $field;
    }

    /** @return array<string,array<string,mixed>>|false Equivalent of get_field_objects(). */
    public static function objects( mixed $context = false, bool $format = true, bool $load_value = true ): array|false {
        if ( Acf::active() ) {
            return get_field_objects( $context, $format, $load_value );
        }
        // As get_field_objects(): each stored value whose `_name` reference resolves to a
        // field of that same name (which leaves out sub-field values).
        $resolved = Storage::context( $context );
        $objects = [];
        foreach ( Storage::referenced( $resolved ) as $name => $key ) {
            $field = FieldGroups::get_field( $key );
            if ( null === $field || (string) $field['name'] !== (string) $name ) {
                continue;
            }
            if ( $load_value ) {
                $value = Values::load( $resolved, $field, (string) $name );
                $field['value'] = $format ? Values::format( $value, $resolved, $field ) : $value;
            }
            $objects[ (string) $name ] = $field;
        }
        return [] === $objects ? false : $objects;
    }

    /* Repeater and flexible-content loops, mirroring have_rows()/the_row(). */

    public static function have_rows( string $selector, mixed $context = false ): bool {
        if ( Acf::active() ) {
            return have_rows( $selector, $context );
        }
        $key = $selector . '|' . serialize( false === $context ? '' : Storage::context( $context ) );
        $top = end( self::$loops );
        if ( false !== $top && $top['selector'] === $selector && $top['context'] === $key ) {
            if ( $top['index'] + 1 < count( $top['rows'] ) ) {
                return true;
            }
            array_pop( self::$loops );
            return false;
        }

        // Nested loop: a sub field of the current row.
        if ( false !== $top && false === $context && $top['index'] >= 0 && array_key_exists( $selector, $top['rows'][ $top['index'] ] ) ) {
            $rows = $top['rows'][ $top['index'] ][ $selector ];
        } else {
            $rows = self::get( $selector, $context );
        }
        if ( ! is_array( $rows ) || [] === $rows ) {
            return false;
        }
        self::$loops[] = [ 'rows' => array_values( $rows ), 'index' => -1, 'selector' => $selector, 'context' => $key ];
        return true;
    }

    /** @return array<string,mixed>|false */
    public static function the_row( bool $format = false ): array|false {
        if ( Acf::active() ) {
            return the_row( $format );
        }
        $i = count( self::$loops ) - 1;
        if ( $i < 0 ) {
            return false;
        }
        self::$loops[ $i ]['index']++;
        return self::$loops[ $i ]['rows'][ self::$loops[ $i ]['index'] ] ?? false;
    }

    public static function get_sub_field( string $selector, bool $format = true ): mixed {
        if ( Acf::active() ) {
            return get_sub_field( $selector, $format );
        }
        $row = self::current_row();
        return is_array( $row ) ? ( $row[ $selector ] ?? null ) : null;
    }

    public static function the_sub_field( string $selector, bool $format = true ): void {
        if ( Acf::active() ) {
            the_sub_field( $selector, $format );
            return;
        }
        $value = self::get_sub_field( $selector, $format );
        echo is_array( $value ) ? esc_html( implode( ', ', array_filter( $value, 'is_scalar' ) ) ) : wp_kses_post( (string) $value ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public static function get_row_index(): int {
        if ( Acf::active() ) {
            return (int) get_row_index();
        }
        $top = end( self::$loops );
        return false === $top ? 0 : $top['index'] + 1;
    }

    public static function get_row_layout(): string|false {
        if ( Acf::active() ) {
            return get_row_layout();
        }
        $row = self::current_row();
        return is_array( $row ) && isset( $row['acf_fc_layout'] ) ? (string) $row['acf_fc_layout'] : false;
    }

    public static function reset_rows(): bool {
        if ( Acf::active() ) {
            return (bool) reset_rows();
        }
        array_pop( self::$loops );
        return true;
    }

    /** @return array<string,mixed>|null */
    private static function current_row(): ?array {
        $top = end( self::$loops );
        if ( false === $top || $top['index'] < 0 ) {
            return null;
        }
        return $top['rows'][ $top['index'] ] ?? null;
    }
}
