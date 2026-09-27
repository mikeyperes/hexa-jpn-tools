<?php

namespace Hexa\PluginCore\Fields;

/**
 * Native edit screens for registered field groups while ACF is not active:
 * post meta boxes, user profiles and taxonomy terms.
 */
final class AdminScreens {
    private static bool $booted = false;
    private static bool $saving = false;

    public static function boot(): void {
        if ( self::$booted ) {
            return;
        }
        self::$booted = true;
        add_action( 'add_meta_boxes', [ self::class, 'meta_boxes' ], 10, 2 );
        add_action( 'save_post', [ self::class, 'save_post' ], 10, 2 );
        add_action( 'show_user_profile', [ self::class, 'user_fields' ] );
        add_action( 'edit_user_profile', [ self::class, 'user_fields' ] );
        add_action( 'user_new_form', [ self::class, 'new_user_fields' ] );
        add_action( 'personal_options_update', [ self::class, 'save_user' ] );
        add_action( 'edit_user_profile_update', [ self::class, 'save_user' ] );
        add_action( 'user_register', [ self::class, 'save_user' ] );
        add_action( 'admin_init', [ self::class, 'term_hooks' ], 99 );
        add_action( 'admin_enqueue_scripts', [ self::class, 'assets' ] );
        add_action( 'admin_footer', [ Renderer::class, 'print_script' ] );
    }

    public static function assets(): void {
        if ( Acf::active() || ! is_admin() ) {
            return;
        }
        wp_enqueue_script( 'jquery' );
        if ( function_exists( 'wp_enqueue_media' ) && ! did_action( 'wp_enqueue_media' ) ) {
            wp_enqueue_media();
        }
    }

    public static function meta_boxes( string $post_type, mixed $post = null ): void {
        if ( Acf::active() || ! $post instanceof \WP_Post ) {
            return;
        }
        foreach ( FieldGroups::for_screen( Storage::screen( [ 'post', $post->ID ] ) ) as $group ) {
            $position = (string) $group['position'];
            add_meta_box(
                'hexa-fields-' . sanitize_html_class( (string) $group['key'] ),
                '' !== (string) $group['title'] ? (string) $group['title'] : 'Fields',
                [ self::class, 'render_meta_box' ],
                $post_type,
                'side' === $position ? 'side' : 'normal',
                'acf_after_title' === $position ? 'high' : 'default',
                [ 'group' => $group ]
            );
        }
    }

    /** @param array<string,mixed> $box */
    public static function render_meta_box( \WP_Post $post, array $box ): void {
        Renderer::nonce();
        Renderer::fields( (array) ( $box['args']['group']['fields'] ?? [] ), [ 'post', $post->ID ] );
    }

    public static function save_post( int $post_id, \WP_Post $post ): void {
        if ( Acf::active() || self::$saving || ! Renderer::verify() ) {
            return;
        }
        if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        self::$saving = true;
        $context = [ 'post', $post_id ];
        Form::save( FieldGroups::for_screen( Storage::screen( $context ) ), $context, Renderer::posted() );
        Hooks::action( 'save_post', $post_id );
        self::$saving = false;
    }

    public static function user_fields( \WP_User $user ): void {
        if ( Acf::active() ) {
            return;
        }
        self::render_sections( FieldGroups::for_screen( [ 'user_form' => 'edit', 'user_roles' => (array) $user->roles ] ), [ 'user', $user->ID ] );
    }

    public static function new_user_fields(): void {
        if ( Acf::active() ) {
            return;
        }
        self::render_sections( FieldGroups::for_screen( [ 'user_form' => 'add', 'user_roles' => [] ] ), null );
    }

    public static function save_user( int $user_id ): void {
        if ( Acf::active() || ! Renderer::verify() || ! current_user_can( 'edit_user', $user_id ) ) {
            return;
        }
        $user = get_userdata( $user_id );
        $context = [ 'user', $user_id ];
        $form = doing_action( 'user_register' ) ? 'add' : 'edit';
        Form::save( FieldGroups::for_screen( [ 'user_form' => $form, 'user_roles' => $user instanceof \WP_User ? (array) $user->roles : [] ] ), $context, Renderer::posted() );
        Hooks::action( 'save_post', 'user_' . $user_id );
    }

    public static function term_hooks(): void {
        if ( Acf::active() ) {
            return;
        }
        foreach ( get_taxonomies() as $taxonomy ) {
            if ( [] === FieldGroups::for_screen( [ 'taxonomy' => $taxonomy ] ) ) {
                continue;
            }
            add_action( $taxonomy . '_add_form_fields', static fn() => self::render_sections( FieldGroups::for_screen( [ 'taxonomy' => $taxonomy ] ), null, 'div' ) );
            add_action( $taxonomy . '_edit_form_fields', static fn( $term ) => self::render_sections( FieldGroups::for_screen( [ 'taxonomy' => $taxonomy ] ), [ 'term', (int) $term->term_id ], 'row' ) );
            add_action( 'created_' . $taxonomy, static fn( $term_id ) => self::save_term( (int) $term_id, $taxonomy ) );
            add_action( 'edited_' . $taxonomy, static fn( $term_id ) => self::save_term( (int) $term_id, $taxonomy ) );
        }
    }

    private static function save_term( int $term_id, string $taxonomy ): void {
        if ( ! Renderer::verify() || ! current_user_can( 'edit_term', $term_id ) ) {
            return;
        }
        $context = [ 'term', $term_id ];
        Form::save( FieldGroups::for_screen( [ 'taxonomy' => $taxonomy ] ), $context, Renderer::posted() );
        Hooks::action( 'save_post', 'term_' . $term_id );
    }

    /**
     * @param array<int,array<string,mixed>> $groups
     * @param array{0:string,1:int|string}|null $context
     */
    private static function render_sections( array $groups, ?array $context, string $layout = 'section' ): void {
        if ( [] === $groups ) {
            return;
        }
        self::assets();
        foreach ( $groups as $group ) {
            if ( 'row' === $layout ) {
                echo '<tr class="form-field"><th scope="row">' . esc_html( (string) $group['title'] ) . '</th><td>';
            } else {
                echo '<h2>' . esc_html( (string) $group['title'] ) . '</h2>';
            }
            Renderer::nonce();
            Renderer::fields( (array) $group['fields'], $context );
            if ( 'row' === $layout ) {
                echo '</td></tr>';
            }
        }
    }
}
