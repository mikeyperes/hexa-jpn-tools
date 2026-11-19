<?php

namespace Hexa\PluginCore\Fields;

/** Reads, formats, updates and iterates fields through the Core engine. */
final class Field {
    /** @var array<int,array{rows:array,field:array,context:array,index:int,selector:string}> */
    private static array $loops = [];

    /** Always true once this Core version is loaded; replaces `function_exists( 'get_field' )` guards. */
    public static function available(): bool {
        return true;
    }

    public static function get( string $selector, mixed $context = false, bool $format = true, bool $escape_html = false ): mixed {
        if ( '' === $selector ) {
            return null;
        }
        // Bug: an installed ACF plugin took over Core reads and applied its own filters.
        // Fix: resolve fields and load values in Core. Why: both engines can run independently.
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
        $value = self::get( $selector, $context, $format );
        echo is_array( $value ) ? esc_html( implode( ', ', array_filter( $value, 'is_scalar' ) ) ) : wp_kses_post( (string) $value ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public static function update( string $selector, mixed $value, mixed $context = false ): bool {
        $context = Storage::context( $context );
        [ $field, $name ] = Values::resolve( $selector, $context, false );
        if ( null === $field ) {
            return Storage::update( $context, $selector, $value );
        }
        return Values::write( $context, $field, $name, $value );
    }

    public static function delete( string $selector, mixed $context = false ): bool {
        $context = Storage::context( $context );
        [ $field, $name ] = Values::resolve( $selector, $context );
        // Like delete_field(): a name with no stored reference is not a field.
        return null === $field ? false : Values::erase( $context, $field, $name );
    }

    /** @return array<string,mixed>|false Equivalent of get_fields(). */
    public static function all( mixed $context = false, bool $format = true ): array|false {
        $values = [];
        foreach ( self::objects( $context, $format ) ?: [] as $name => $object ) {
            $values[ $name ] = $object['value'];
        }
        return [] === $values ? false : $values;
    }

    /** @return array<string,mixed>|false Equivalent of get_field_object(). */
    public static function object( string $selector, mixed $context = false, bool $format = true, bool $load_value = true ): array|false {
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

    /** Starts a loop or checks whether the current loop has another row. */
    public static function have_rows( string $selector, mixed $context = false ): bool {
        $top = end( self::$loops );
        $resolved = false === $context && false !== $top ? $top['context'] : Storage::context( $context );
        if ( false !== $top && $top['selector'] === $selector && $top['context'] === $resolved ) {
            if ( $top['index'] + 1 < count( $top['rows'] ) ) {
                return true;
            }
            array_pop( self::$loops );
            return false;
        }

        // Bug: storing formatted rows loses the raw values and the subfield keys.
        // Fix: keep raw rows with the field definition. Why: the callers choose the return format.
        $field = false === $context ? self::sub_field( $selector ) : null;
        if ( null !== $field ) {
            $raw = self::current_row()[ $field['key'] ] ?? null;
        } else {
            [ $field, $name ] = Values::resolve( $selector, $resolved );
            $raw = null === $field ? null : Values::load( $resolved, $field, $name );
        }
        if ( ! is_array( $raw ) || [] === $raw || ! in_array( $field['type'], [ 'group', 'repeater', 'flexible_content' ], true ) ) {
            return false;
        }
        $rows = 'group' === $field['type'] ? [ $raw ] : array_values( $raw );
        self::$loops[] = [ 'rows' => $rows, 'field' => $field, 'context' => $resolved, 'index' => -1, 'selector' => $selector ];
        return true;
    }

    /** Advances the active loop and returns its row. */
    public static function the_row( bool $format = false ): array|false {
        $index = array_key_last( self::$loops );
        if ( null === $index ) {
            return false;
        }
        self::$loops[ $index ]['index']++;
        return self::get_row( $format );
    }

    /** Returns the current row, optionally formatted and keyed by name. */
    public static function get_row( bool $format = false ): array|false {
        $row = self::current_row();
        if ( null === $row ) {
            return false;
        }
        if ( ! $format ) {
            return $row;
        }
        $loop = end( self::$loops );
        $field = $loop['field'];
        if ( 'group' === $field['type'] ) {
            return Values::format( $row, $loop['context'], $field );
        }
        $formatted = Values::format( [ $row ], $loop['context'], $field );
        return $formatted[0] ?? false;
    }

    /** Reads a child field by name or key in the current row. */
    public static function get_sub_field( string $selector, bool $format = true, bool $escape_html = false ): mixed {
        $field = self::sub_field( $selector );
        if ( null === $field ) {
            return null;
        }
        $value = self::current_row()[ $field['key'] ] ?? null;
        $loop = end( self::$loops );
        $value = $format ? Values::format( $value, $loop['context'], $field ) : $value;
        return $escape_html && is_string( $value ) ? wp_kses_post( $value ) : $value;
    }

    /** Resolves a definition in the current row's group or flexible layout. */
    private static function sub_field( string $selector ): ?array {
        $row = self::current_row();
        if ( null === $row ) {
            return null;
        }
        $loop = end( self::$loops );
        $fields = 'flexible_content' === $loop['field']['type']
            ? Values::layout_fields( $loop['field'], (string) ( $row['acf_fc_layout'] ?? '' ) )
            : (array) ( $loop['field']['sub_fields'] ?? [] );
        foreach ( $fields as $sub ) {
            if ( $selector === $sub['key'] || $selector === $sub['name'] ) {
                return $sub;
            }
        }
        return null;
    }

    public static function the_sub_field( string $selector, bool $format = true ): void {
        $value = self::get_sub_field( $selector, $format );
        echo is_array( $value ) ? esc_html( implode( ', ', array_filter( $value, 'is_scalar' ) ) ) : wp_kses_post( (string) $value ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public static function get_row_index(): int {
        $top = end( self::$loops );
        return false === $top ? 0 : $top['index'] + 1;
    }

    public static function get_row_layout(): string|false {
        $row = self::current_row();
        return is_array( $row ) && isset( $row['acf_fc_layout'] ) ? (string) $row['acf_fc_layout'] : false;
    }

    public static function reset_rows(): bool {
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
