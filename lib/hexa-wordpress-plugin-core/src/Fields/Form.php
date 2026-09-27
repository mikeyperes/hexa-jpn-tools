<?php

namespace Hexa\PluginCore\Fields;

/**
 * Field forms and saving: `head()`/`render()` mirror `acf_form_head()` and
 * `acf_form()`, delegating to ACF when it is active.
 */
final class Form {
    /** @var array<string,mixed> */
    private static array $data = [];

    public static function head(): void {
        if ( Acf::active() ) {
            if ( function_exists( 'acf_form_head' ) ) {
                acf_form_head();
            }
            return;
        }
        self::handle();
    }

    public static function enqueue(): void {
        if ( Acf::active() ) {
            if ( function_exists( 'acf_enqueue_scripts' ) ) {
                acf_enqueue_scripts();
            }
            return;
        }
        AdminScreens::assets();
    }

    public static function data( string $key = '' ): mixed {
        if ( Acf::active() && function_exists( 'acf_get_form_data' ) ) {
            return acf_get_form_data( $key );
        }
        return '' === $key ? self::$data : ( self::$data[ $key ] ?? null );
    }

    /**
     * One field outside a form, as acf_render_field_wrap(): it shows
     * `$field['value']` and posts under `<prefix>[<key>]` (prefix `acf` by
     * default) for the host to read and save.
     *
     * @param array<string,mixed> $field
     */
    public static function field( array $field, string $element = 'div', string $instruction = 'label' ): void {
        if ( Acf::active() ) {
            if ( function_exists( 'acf_render_field_wrap' ) ) {
                acf_render_field_wrap( $field, $element, $instruction );
            }
            return;
        }
        $field = array_merge( [ 'key' => '', 'name' => '', 'label' => '', 'type' => 'text', 'instructions' => '', 'required' => 0, 'prefix' => 'acf' ], $field );
        AdminScreens::assets();
        Renderer::field( $field, $field['value'] ?? null, (string) $field['prefix'] . '[' . (string) $field['key'] . ']', 0 );
        Renderer::standalone();
    }

    /** @param array<string,mixed> $args acf_form() arguments. */
    public static function render( array $args = [] ): void {
        if ( Acf::active() ) {
            if ( function_exists( 'acf_form' ) ) {
                acf_form( $args );
            }
            return;
        }
        $args = array_merge(
            [
                'post_id' => false, 'field_groups' => [], 'fields' => [], 'form' => true, 'form_attributes' => [],
                'return' => '', 'html_before_fields' => '', 'html_after_fields' => '', 'submit_value' => 'Update',
                'updated_message' => 'Post updated', 'html_updated_message' => '<div id="message" class="updated"><p>%s</p></div>',
                'html_submit_button' => '<input type="submit" class="button button-primary button-large" value="%s" />',
            ],
            $args
        );
        $context = Storage::context( $args['post_id'] );
        $fields = self::select_fields( $args, $context );
        AdminScreens::assets();

        if ( isset( $_GET['updated'] ) && 'true' === $_GET['updated'] && '' !== (string) $args['updated_message'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            printf( (string) $args['html_updated_message'], esc_html( (string) $args['updated_message'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        if ( ! empty( $args['form'] ) ) {
            $attributes = '';
            foreach ( (array) $args['form_attributes'] as $attribute => $value ) {
                $attributes .= ' ' . esc_attr( (string) $attribute ) . '="' . esc_attr( (string) $value ) . '"';
            }
            echo '<form method="post" action=""' . $attributes . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            $payload = wp_json_encode( [ 'post_id' => Storage::acf_id( $context ), 'field_groups' => array_values( (array) $args['field_groups'] ), 'fields' => array_values( (array) $args['fields'] ), 'return' => (string) $args['return'] ] );
            echo '<input type="hidden" name="hexa_fields_form" value="' . esc_attr( base64_encode( (string) $payload ) ) . '"><input type="hidden" name="hexa_fields_form_sig" value="' . esc_attr( wp_hash( (string) $payload, 'nonce' ) ) . '">';
        }
        Renderer::nonce();
        echo $args['html_before_fields']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        Renderer::fields( $fields, $context );
        echo $args['html_after_fields']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        if ( ! empty( $args['form'] ) ) {
            echo '<div class="hexa-form-submit">';
            printf( (string) $args['html_submit_button'], esc_attr( (string) $args['submit_value'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '</div></form>';
        }
        Renderer::print_script();
    }

    /**
     * Save posted values for the given groups.
     *
     * @param array<int,array<string,mixed>> $groups
     * @param array{0:string,1:int|string} $context
     * @param array<string,mixed> $input Unslashed values keyed by field key.
     * @param array<int,string>|null $only Field keys or names to limit saving.
     */
    public static function save( array $groups, array $context, array $input, ?array $only = null ): void {
        foreach ( $groups as $group ) {
            foreach ( (array) $group['fields'] as $field ) {
                if ( ! array_key_exists( $field['key'], $input ) ) {
                    continue;
                }
                if ( null !== $only && ! in_array( $field['key'], $only, true ) && ! in_array( $field['name'], $only, true ) ) {
                    continue;
                }
                Values::write( $context, $field, (string) $field['name'], self::sanitize( $field, $input[ $field['key'] ] ) );
            }
        }
    }

    /** @param array<string,mixed> $field */
    public static function sanitize( array $field, mixed $value ): mixed {
        $type = (string) $field['type'];
        switch ( $type ) {
            case 'repeater':
                $rows = [];
                foreach ( is_array( $value ) ? $value : [] as $row ) {
                    if ( ! is_array( $row ) ) {
                        continue;
                    }
                    $clean = [];
                    foreach ( (array) ( $field['sub_fields'] ?? [] ) as $sub ) {
                        if ( array_key_exists( $sub['key'], $row ) ) {
                            $clean[ $sub['key'] ] = self::sanitize( $sub, $row[ $sub['key'] ] );
                        }
                    }
                    $rows[] = $clean;
                }
                return $rows;
            case 'group':
                $clean = [];
                foreach ( (array) ( $field['sub_fields'] ?? [] ) as $sub ) {
                    if ( is_array( $value ) && array_key_exists( $sub['key'], $value ) ) {
                        $clean[ $sub['key'] ] = self::sanitize( $sub, $value[ $sub['key'] ] );
                    }
                }
                return $clean;
            case 'textarea':
                return sanitize_textarea_field( (string) $value );
            case 'wysiwyg':
                return current_user_can( 'unfiltered_html' ) ? (string) $value : wp_kses_post( (string) $value );
            case 'email':
                return sanitize_email( (string) $value );
            case 'url':
            case 'oembed':
                return esc_url_raw( (string) $value );
            case 'number':
            case 'range':
                return is_numeric( $value ) ? (string) $value : '';
            case 'true_false':
                return '1' === (string) $value ? 1 : 0;
            case 'gallery':
                return array_values( array_filter( array_map( 'absint', is_array( $value ) ? $value : explode( ',', (string) $value ) ) ) );
            case 'image':
            case 'file':
                return absint( $value ) ?: '';
            case 'link':
                $value = is_array( $value ) ? $value : [];
                $url = esc_url_raw( (string) ( $value['url'] ?? '' ) );
                return '' === $url ? '' : [ 'title' => sanitize_text_field( (string) ( $value['title'] ?? '' ) ), 'url' => $url, 'target' => '_blank' === ( $value['target'] ?? '' ) ? '_blank' : '' ];
            default:
                if ( is_array( $value ) ) {
                    return array_values( array_filter( array_map( static fn( $item ) => sanitize_text_field( (string) $item ), $value ), static fn( $item ) => '' !== $item ) );
                }
                return sanitize_text_field( (string) $value );
        }
    }

    /**
     * Groups for a context, optionally restricted to explicit group keys.
     *
     * @param array<int,string> $keys
     * @param array{0:string,1:int|string} $context
     * @return array<int,array<string,mixed>>
     */
    public static function groups( array $keys, array $context ): array {
        if ( [] === $keys ) {
            return FieldGroups::for_screen( Storage::screen( $context ) );
        }
        return array_values( array_filter( array_map( static fn( $key ) => FieldGroups::get_group( (string) $key ), $keys ) ) );
    }

    /**
     * @param array<string,mixed> $args
     * @param array{0:string,1:int|string} $context
     * @return array<int,array<string,mixed>>
     */
    private static function select_fields( array $args, array $context ): array {
        $only = array_values( array_filter( array_map( 'strval', (array) $args['fields'] ) ) );
        $fields = [];
        foreach ( self::groups( array_map( 'strval', (array) $args['field_groups'] ), $context ) as $group ) {
            foreach ( (array) $group['fields'] as $field ) {
                if ( [] === $only || in_array( $field['key'], $only, true ) || in_array( $field['name'], $only, true ) ) {
                    $fields[] = $field;
                }
            }
        }
        if ( [] === $fields && [] !== $only ) {
            foreach ( $only as $selector ) {
                $field = FieldGroups::get_field( $selector );
                if ( null !== $field ) {
                    $fields[] = $field;
                }
            }
        }
        return $fields;
    }

    private static function handle(): void {
        if ( empty( $_POST['hexa_fields_form'] ) || empty( $_POST['hexa_fields_form_sig'] ) || ! Renderer::verify() ) {
            return;
        }
        $payload = (string) base64_decode( sanitize_text_field( wp_unslash( $_POST['hexa_fields_form'] ) ), true );
        if ( ! hash_equals( wp_hash( $payload, 'nonce' ), sanitize_text_field( wp_unslash( $_POST['hexa_fields_form_sig'] ) ) ) ) {
            return;
        }
        $form = json_decode( $payload, true );
        if ( ! is_array( $form ) ) {
            return;
        }
        $context = Storage::context( $form['post_id'] ?? false );
        if ( ! self::can_edit( $context ) ) {
            wp_die( esc_html__( 'Sorry, you are not allowed to edit these fields.' ), 403 );
        }
        self::$data = $form;
        $only = array_values( array_filter( array_map( 'strval', (array) ( $form['fields'] ?? [] ) ) ) );
        self::save( self::groups( array_map( 'strval', (array) ( $form['field_groups'] ?? [] ) ), $context ), $context, Renderer::posted(), [] === $only ? null : $only );
        Hooks::action( 'save_post', Storage::acf_id( $context ) );
        $return = '' !== (string) ( $form['return'] ?? '' ) ? (string) $form['return'] : add_query_arg( 'updated', 'true', wp_get_referer() ?: home_url( add_query_arg( [] ) ) );
        wp_safe_redirect( $return );
        exit;
    }

    /** @param array{0:string,1:int|string} $context */
    public static function can_edit( array $context ): bool {
        [ $type, $id ] = $context;
        switch ( $type ) {
            case 'post':
                return current_user_can( 'edit_post', (int) $id );
            case 'user':
                return current_user_can( 'edit_user', (int) $id );
            case 'term':
                return current_user_can( 'edit_term', (int) $id );
            case 'comment':
                return current_user_can( 'edit_comment', (int) $id );
            default:
                return current_user_can( 'manage_options' );
        }
    }
}
