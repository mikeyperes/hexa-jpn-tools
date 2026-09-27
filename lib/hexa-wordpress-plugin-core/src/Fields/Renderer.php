<?php

namespace Hexa\PluginCore\Fields;

/**
 * Native editing controls for ACF-format fields. Inputs post under
 * `hexa_fields[<field_key>]`, nested by sub-field key and row index.
 */
final class Renderer {
    public const INPUT = 'hexa_fields';
    public const NONCE_ACTION = 'hexa_fields_save';
    public const NONCE_FIELD = 'hexa_fields_nonce';

    private static bool $nonce_printed = false;
    private static bool $script_printed = false;
    private static bool $standalone = false;

    /** A field rendered outside a Fields form still needs the editing script. */
    public static function standalone(): void {
        self::$standalone = true;
        add_action( 'admin_footer', [ self::class, 'print_script' ] );
    }

    public static function nonce(): void {
        if ( self::$nonce_printed ) {
            return;
        }
        self::$nonce_printed = true;
        wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
    }

    public static function verify(): bool {
        return isset( $_POST[ self::NONCE_FIELD ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION );
    }

    /** @return array<string,mixed> Unslashed posted field values keyed by field key. */
    public static function posted(): array {
        return isset( $_POST[ self::INPUT ] ) && is_array( $_POST[ self::INPUT ] ) ? (array) wp_unslash( $_POST[ self::INPUT ] ) : [];
    }

    /**
     * @param array<int,array<string,mixed>> $fields
     * @param array{0:string,1:int|string}|null $context Null renders defaults (new objects).
     */
    public static function fields( array $fields, ?array $context, string $base = self::INPUT ): void {
        echo '<div class="hexa-fields">';
        foreach ( $fields as $field ) {
            $field = Hooks::field_filter( 'prepare_field', $field, $field );
            if ( ! is_array( $field ) ) {
                continue;
            }
            $value = null === $context ? ( $field['default_value'] ?? null ) : Values::load( $context, $field, (string) $field['name'] );
            self::field( $field, $value, $base . '[' . $field['key'] . ']', 0 );
        }
        echo '</div>';
    }

    /** @param array<string,mixed> $field */
    public static function field( array $field, mixed $value, string $name, int $depth ): void {
        $type = (string) $field['type'];
        if ( 'accordion' === $type || 'tab' === $type ) {
            if ( '' !== (string) $field['label'] ) {
                echo '<h3 class="hexa-field-heading">' . esc_html( (string) $field['label'] ) . '</h3>';
            }
            return;
        }
        if ( 'message' === $type ) {
            echo '<div class="hexa-field-message">' . wp_kses_post( wpautop( (string) ( $field['message'] ?? '' ) ) ) . '</div>';
            return;
        }
        $id = 'hexa-f-' . substr( md5( $name ), 0, 12 );
        echo '<div class="hexa-field hexa-field-' . esc_attr( $type ) . '">';
        if ( 'true_false' !== $type && '' !== (string) $field['label'] ) {
            echo '<label class="hexa-field-label" for="' . esc_attr( $id ) . '">' . esc_html( (string) $field['label'] ) . ( ! empty( $field['required'] ) ? ' <span class="required">*</span>' : '' ) . '</label>';
        }
        self::input( $field, $value, $name, $id, $depth );
        if ( '' !== (string) ( $field['instructions'] ?? '' ) ) {
            echo '<p class="description">' . wp_kses_post( (string) $field['instructions'] ) . '</p>';
        }
        Hooks::field_action( 'render_field', $field + [ 'value' => $value, 'input_name' => $name, 'id' => $id ] );
        echo '</div>';
    }

    /** @param array<string,mixed> $field */
    private static function input( array $field, mixed $value, string $name, string $id, int $depth ): void {
        $type = (string) $field['type'];
        $attrs = self::attrs( $field, $id );
        switch ( $type ) {
            case 'textarea':
                echo '<textarea class="widefat" name="' . esc_attr( $name ) . '" rows="' . esc_attr( (string) ( $field['rows'] ?? 4 ) ) . '"' . $attrs . '>' . esc_textarea( (string) $value ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                return;
            case 'wysiwyg':
                if ( $depth > 0 || str_contains( $name, '__hexa_row_' ) ) {
                    echo '<textarea class="widefat" name="' . esc_attr( $name ) . '" rows="6"' . $attrs . '>' . esc_textarea( (string) $value ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    return;
                }
                wp_editor( (string) $value, str_replace( '-', '_', $id ), [ 'textarea_name' => $name, 'textarea_rows' => 8, 'media_buttons' => ! empty( $field['media_upload'] ) ] );
                return;
            case 'true_false':
                echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0"><label><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . checked( (bool) (int) $value, true, false ) . $attrs . '> <strong>' . esc_html( (string) $field['label'] ) . '</strong>' . ( '' !== (string) ( $field['message'] ?? '' ) ? ' — ' . esc_html( (string) $field['message'] ) : '' ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                return;
            case 'select':
                $multiple = ! empty( $field['multiple'] );
                $selected = array_map( 'strval', (array) $value );
                echo '<select name="' . esc_attr( $name . ( $multiple ? '[]' : '' ) ) . '"' . ( $multiple ? ' multiple size="6"' : '' ) . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                if ( ! $multiple && ( ! empty( $field['allow_null'] ) || empty( $field['required'] ) ) ) {
                    echo '<option value="">— Select —</option>';
                }
                foreach ( (array) ( $field['choices'] ?? [] ) as $choice => $label ) {
                    echo '<option value="' . esc_attr( (string) $choice ) . '"' . selected( in_array( (string) $choice, $selected, true ), true, false ) . '>' . esc_html( (string) $label ) . '</option>';
                }
                echo '</select>';
                return;
            case 'radio':
            case 'button_group':
                foreach ( (array) ( $field['choices'] ?? [] ) as $choice => $label ) {
                    echo '<label class="hexa-choice"><input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $choice ) . '"' . checked( (string) $value, (string) $choice, false ) . '> ' . esc_html( (string) $label ) . '</label>';
                }
                return;
            case 'checkbox':
                $selected = array_map( 'strval', (array) $value );
                echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="">';
                foreach ( (array) ( $field['choices'] ?? [] ) as $choice => $label ) {
                    echo '<label class="hexa-choice"><input type="checkbox" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( (string) $choice ) . '"' . checked( in_array( (string) $choice, $selected, true ), true, false ) . '> ' . esc_html( (string) $label ) . '</label>';
                }
                return;
            case 'image':
            case 'file':
            case 'gallery':
                self::media( $field, $value, $name );
                return;
            case 'post_object':
            case 'page_link':
            case 'relationship':
                $multiple = 'relationship' === $type || ! empty( $field['multiple'] );
                $types = array_filter( (array) ( $field['post_type'] ?? [] ) );
                $posts = get_posts( [ 'post_type' => $types ?: 'any', 'post_status' => [ 'publish', 'draft', 'pending', 'private', 'future' ], 'numberposts' => 300, 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => false ] );
                $options = [];
                foreach ( $posts as $post ) {
                    $options[ $post->ID ] = $post->post_title . ( 'publish' !== $post->post_status ? ' (' . $post->post_status . ')' : '' );
                }
                foreach ( array_map( 'intval', (array) $value ) as $selected_id ) {
                    if ( $selected_id > 0 && ! isset( $options[ $selected_id ] ) ) {
                        $options[ $selected_id ] = get_the_title( $selected_id ) ?: '#' . $selected_id;
                    }
                }
                self::select( $name, $options, (array) $value, $multiple, $attrs );
                return;
            case 'user':
                $roles = array_filter( (array) ( $field['role'] ?? [] ) );
                $users = get_users( array_filter( [ 'number' => 500, 'orderby' => 'display_name', 'fields' => [ 'ID', 'display_name' ], 'role__in' => $roles ] ) );
                $options = [];
                foreach ( $users as $user ) {
                    $options[ (int) $user->ID ] = $user->display_name;
                }
                self::select( $name, $options, (array) $value, ! empty( $field['multiple'] ), $attrs );
                return;
            case 'taxonomy':
                $terms = get_terms( [ 'taxonomy' => (string) ( $field['taxonomy'] ?? 'category' ), 'hide_empty' => false, 'number' => 500 ] );
                $options = [];
                foreach ( is_array( $terms ) ? $terms : [] as $term ) {
                    $options[ (int) $term->term_id ] = $term->name;
                }
                self::select( $name, $options, (array) $value, in_array( (string) ( $field['field_type'] ?? 'checkbox' ), [ 'checkbox', 'multi_select' ], true ), $attrs );
                return;
            case 'link':
                $link = is_array( $value ) ? array_merge( [ 'url' => '', 'title' => '', 'target' => '' ], $value ) : [ 'url' => (string) $value, 'title' => '', 'target' => '' ];
                echo '<div class="hexa-link"><input type="url" class="regular-text" placeholder="URL" name="' . esc_attr( $name ) . '[url]" value="' . esc_attr( (string) $link['url'] ) . '"> <input type="text" class="regular-text" placeholder="Link text" name="' . esc_attr( $name ) . '[title]" value="' . esc_attr( (string) $link['title'] ) . '"> <label><input type="checkbox" name="' . esc_attr( $name ) . '[target]" value="_blank"' . checked( '_blank', (string) $link['target'], false ) . '> New tab</label></div>';
                return;
            case 'group':
                echo '<fieldset class="hexa-group">';
                foreach ( (array) ( $field['sub_fields'] ?? [] ) as $sub ) {
                    $sub = Hooks::field_filter( 'prepare_field', $sub, $sub );
                    if ( is_array( $sub ) ) {
                        self::field( $sub, is_array( $value ) ? ( $value[ $sub['key'] ] ?? null ) : null, $name . '[' . $sub['key'] . ']', $depth + 1 );
                    }
                }
                echo '</fieldset>';
                return;
            case 'repeater':
                self::repeater( $field, $value, $name, $depth );
                return;
            case 'flexible_content':
            case 'clone':
            case 'google_map':
                echo '<p class="description">This field type is edited with ACF. Its stored value is kept unchanged.</p>';
                return;
            default:
                $html = [ 'email' => 'email', 'url' => 'url', 'oembed' => 'url', 'number' => 'number', 'range' => 'number', 'password' => 'password', 'date_picker' => 'date', 'date_time_picker' => 'datetime-local', 'time_picker' => 'time' ][ $type ] ?? 'text';
                $shown = (string) ( is_scalar( $value ) ? $value : '' );
                if ( 'date_picker' === $type && preg_match( '/^\d{8}$/', $shown ) ) {
                    $shown = substr( $shown, 0, 4 ) . '-' . substr( $shown, 4, 2 ) . '-' . substr( $shown, 6, 2 );
                } elseif ( 'date_time_picker' === $type && '' !== $shown ) {
                    $shown = str_replace( ' ', 'T', substr( $shown, 0, 16 ) );
                }
                foreach ( [ 'min', 'max', 'step' ] as $limit ) {
                    if ( isset( $field[ $limit ] ) && '' !== (string) $field[ $limit ] && 'number' === $html ) {
                        $attrs .= ' ' . $limit . '="' . esc_attr( (string) $field[ $limit ] ) . '"';
                    }
                }
                echo '<input type="' . esc_attr( $html ) . '" class="' . ( 'color_picker' === $type ? 'hexa-color' : 'widefat' ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $shown ) . '"' . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }

    /** @param array<string,mixed> $field */
    private static function repeater( array $field, mixed $value, string $name, int $depth ): void {
        $rows = is_array( $value ) ? array_values( $value ) : [];
        $placeholder = '__hexa_row_' . $depth . '__';
        $subs = (array) ( $field['sub_fields'] ?? [] );
        echo '<div class="hexa-repeater" data-next="' . esc_attr( (string) count( $rows ) ) . '" data-placeholder="' . esc_attr( $placeholder ) . '"><table class="widefat striped"><tbody class="hexa-repeater-rows">';
        foreach ( $rows as $i => $row ) {
            self::repeater_row( $subs, is_array( $row ) ? $row : [], $name . '[' . $i . ']', $depth, (string) ( $i + 1 ) );
        }
        echo '</tbody></table><template class="hexa-repeater-template">';
        self::repeater_row( $subs, [], $name . '[' . $placeholder . ']', $depth, '+' );
        echo '</template><p><button type="button" class="button hexa-repeater-add">' . esc_html( (string) ( $field['button_label'] ?? '' ) ?: 'Add Row' ) . '</button></p></div>';
    }

    /**
     * @param array<int,array<string,mixed>> $subs
     * @param array<string,mixed> $row
     */
    private static function repeater_row( array $subs, array $row, string $name, int $depth, string $label ): void {
        echo '<tr class="hexa-repeater-row"><td class="hexa-repeater-index">' . esc_html( $label ) . '</td><td>';
        foreach ( $subs as $sub ) {
            $sub = Hooks::field_filter( 'prepare_field', $sub, $sub );
            if ( is_array( $sub ) ) {
                self::field( $sub, $row[ $sub['key'] ] ?? ( $row ? null : ( $sub['default_value'] ?? null ) ), $name . '[' . $sub['key'] . ']', $depth + 1 );
            }
        }
        echo '</td><td class="hexa-repeater-actions"><button type="button" class="button-link hexa-repeater-remove" aria-label="Remove row">✕</button></td></tr>';
    }

    /** @param array<string,mixed> $field */
    private static function media( array $field, mixed $value, string $name ): void {
        $gallery = 'gallery' === $field['type'];
        $ids = array_values( array_filter( array_map( 'intval', is_array( $value ) ? $value : ( '' === (string) $value ? [] : explode( ',', (string) $value ) ) ) ) );
        $library = 'file' === $field['type'] ? '' : 'image';
        echo '<div class="hexa-media' . ( $gallery ? ' hexa-gallery' : '' ) . '" data-library="' . esc_attr( $library ) . '"><input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( implode( ',', $ids ) ) . '"><div class="hexa-media-preview">';
        foreach ( $ids as $id ) {
            echo wp_attachment_is_image( $id ) ? wp_get_attachment_image( $id, 'thumbnail', true, [ 'style' => 'width:80px;height:auto;margin:0 6px 6px 0' ] ) : '<a href="' . esc_url( (string) wp_get_attachment_url( $id ) ) . '" target="_blank">' . esc_html( wp_basename( (string) get_attached_file( $id ) ) ) . '</a>';
        }
        echo '</div><button type="button" class="button hexa-media-select">' . ( $gallery ? 'Add images' : 'Select' ) . '</button> <button type="button" class="button-link hexa-media-clear">Remove</button></div>';
    }

    /**
     * @param array<int|string,string> $options
     * @param array<int,mixed> $selected
     */
    private static function select( string $name, array $options, array $selected, bool $multiple, string $attrs ): void {
        $selected = array_map( 'strval', array_map( static fn( $item ) => is_object( $item ) ? ( $item->ID ?? $item->term_id ?? '' ) : ( is_array( $item ) ? ( $item['ID'] ?? $item['id'] ?? '' ) : $item ), $selected ) );
        echo '<select name="' . esc_attr( $name . ( $multiple ? '[]' : '' ) ) . '"' . ( $multiple ? ' multiple size="6"' : '' ) . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        if ( ! $multiple ) {
            echo '<option value="">— Select —</option>';
        } else {
            echo '<option value="" disabled>— Select one or more —</option>';
        }
        foreach ( $options as $id => $label ) {
            echo '<option value="' . esc_attr( (string) $id ) . '"' . selected( in_array( (string) $id, $selected, true ), true, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
    }

    /** @param array<string,mixed> $field */
    private static function attrs( array $field, string $id ): string {
        $attrs = ' id="' . esc_attr( $id ) . '"';
        if ( '' !== (string) ( $field['placeholder'] ?? '' ) ) {
            $attrs .= ' placeholder="' . esc_attr( (string) $field['placeholder'] ) . '"';
        }
        if ( ! empty( $field['readonly'] ) ) {
            $attrs .= ' readonly';
        }
        if ( ! empty( $field['disabled'] ) ) {
            $attrs .= ' disabled';
        }
        if ( ! empty( $field['maxlength'] ) ) {
            $attrs .= ' maxlength="' . esc_attr( (string) $field['maxlength'] ) . '"';
        }
        return $attrs;
    }

    public static function print_script(): void {
        if ( self::$script_printed || ! ( self::$nonce_printed || self::$standalone ) ) {
            return;
        }
        self::$script_printed = true;
        ?>
        <style>.hexa-fields .hexa-field{margin:0 0 14px}.hexa-field-label{display:block;font-weight:600;margin:0 0 4px}.hexa-field .description{margin:4px 0 0}.hexa-choice{display:inline-block;margin:0 14px 4px 0}.hexa-group{border:1px solid #dcdcde;padding:10px 12px;margin:0}.hexa-repeater table{margin:0 0 6px}.hexa-repeater-index{width:24px;color:#646970}.hexa-repeater-actions{width:24px}.hexa-field-heading{margin:18px 0 8px;padding-top:10px;border-top:1px solid #dcdcde}.hexa-link input[type=url],.hexa-link input[type=text]{margin:0 6px 6px 0}</style>
        <script>
        jQuery(function($){
            $(document).on('click','.hexa-repeater-add',function(e){e.preventDefault();var r=$(this).closest('.hexa-repeater'),n=parseInt(r.attr('data-next')||'0',10),ph=r.attr('data-placeholder'),tpl=r.children('template.hexa-repeater-template').html()||'';r.attr('data-next',n+1);r.find('tbody.hexa-repeater-rows').first().append(tpl.split(ph).join(String(n)));});
            $(document).on('click','.hexa-repeater-remove',function(e){e.preventDefault();$(this).closest('tr.hexa-repeater-row').remove();});
            $(document).on('click','.hexa-media-select',function(e){e.preventDefault();if(!window.wp||!wp.media){return;}var w=$(this).closest('.hexa-media'),multi=w.hasClass('hexa-gallery'),lib=w.attr('data-library');var frame=wp.media({multiple:multi,library:lib?{type:lib}:{}});frame.on('select',function(){var sel=frame.state().get('selection').toJSON(),input=w.children('input[type=hidden]'),box=w.children('.hexa-media-preview');var thumb=function(a){return a.type==='image'?'<img src="'+((a.sizes&&a.sizes.thumbnail)?a.sizes.thumbnail.url:a.url)+'" style="width:80px;height:auto;margin:0 6px 6px 0">':'<a href="'+a.url+'" target="_blank">'+a.filename+'</a>';};if(multi){var ids=(input.val()||'').split(',').filter(Boolean);sel.forEach(function(a){ids.push(String(a.id));box.append(thumb(a));});input.val(ids.join(','));}else if(sel[0]){input.val(sel[0].id);box.html(thumb(sel[0]));}});frame.open();});
            $(document).on('click','.hexa-media-clear',function(e){e.preventDefault();var w=$(this).closest('.hexa-media');w.children('input[type=hidden]').val('');w.children('.hexa-media-preview').empty();});
        });
        </script>
        <?php
    }
}
