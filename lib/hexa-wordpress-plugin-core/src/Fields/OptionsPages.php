<?php

namespace Hexa\PluginCore\Fields;

/**
 * Options pages: `add()`/`add_sub()` mirror `acf_add_options_page()` and
 * `acf_add_options_sub_page()`, delegating to ACF when it is active.
 * Native pages store values under `{post_id}_{name}` options, as ACF does.
 */
final class OptionsPages {
    /** @var array<string,array<string,mixed>> */
    private static array $pages = [];
    private static bool $hooked = false;

    /** @param array<string,mixed>|string $page */
    public static function add( array|string $page = [] ): array {
        $page = is_string( $page ) ? [ 'page_title' => $page ] : $page;
        if ( ! did_action( 'init' ) && ! doing_action( 'init' ) ) {
            FieldGroups::ready( static function () use ( $page ): void {
                self::add( $page );
            } );
            return self::normalize( $page );
        }
        if ( Acf::active() ) {
            $function = '' !== (string) ( $page['parent_slug'] ?? '' ) ? 'acf_add_options_sub_page' : 'acf_add_options_page';
            return function_exists( $function ) ? (array) $function( $page ) : $page;
        }
        $page = self::normalize( $page );
        self::$pages[ $page['menu_slug'] ] = $page;
        AdminScreens::boot();
        if ( ! self::$hooked ) {
            self::$hooked = true;
            add_action( 'admin_menu', [ self::class, 'menus' ], 99 );
            add_action( 'admin_init', [ self::class, 'save' ] );
        }
        return $page;
    }

    /** @param array<string,mixed>|string $page */
    public static function add_sub( array|string $page = [] ): array {
        $page = is_string( $page ) ? [ 'page_title' => $page ] : $page;
        if ( '' === (string) ( $page['parent_slug'] ?? '' ) ) {
            $parents = array_filter( self::$pages, static fn( array $existing ): bool => '' === $existing['parent_slug'] );
            $page['parent_slug'] = $parents ? (string) array_key_first( $parents ) : 'options-general.php';
        }
        return self::add( $page );
    }

    /** @return array<string,array<string,mixed>> */
    public static function pages(): array {
        return self::$pages;
    }

    public static function menus(): void {
        foreach ( self::$pages as $slug => $page ) {
            $render = static function () use ( $slug ): void {
                self::render( $slug );
            };
            if ( '' === $page['parent_slug'] ) {
                add_menu_page( $page['page_title'], $page['menu_title'], $page['capability'], $slug, $render, (string) $page['icon_url'], $page['position'] );
            } else {
                add_submenu_page( $page['parent_slug'], $page['page_title'], $page['menu_title'], $page['capability'], $slug, $render, $page['position'] );
            }
        }
    }

    public static function render( string $slug ): void {
        $page = self::$pages[ $slug ] ?? null;
        if ( null === $page || ! current_user_can( $page['capability'] ) ) {
            return;
        }
        $context = Storage::context( $page['post_id'] );
        AdminScreens::assets();
        echo '<div class="wrap"><h1>' . esc_html( $page['page_title'] ) . '</h1>';
        if ( isset( $_GET['updated'] ) && 'true' === $_GET['updated'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $page['updated_message'] ) . '</p></div>';
        }
        echo '<form method="post" action=""><input type="hidden" name="hexa_fields_options_page" value="' . esc_attr( $slug ) . '">';
        Renderer::nonce();
        foreach ( FieldGroups::for_screen( [ 'options_page' => $slug ] ) as $group ) {
            echo '<div class="postbox" style="padding:0 14px 6px;margin-top:14px"><h2>' . esc_html( (string) $group['title'] ) . '</h2>';
            Renderer::fields( (array) $group['fields'], $context );
            echo '</div>';
        }
        submit_button( $page['update_button'] );
        echo '</form></div>';
        Renderer::print_script();
    }

    public static function save(): void {
        if ( empty( $_POST['hexa_fields_options_page'] ) || Acf::active() ) {
            return;
        }
        $slug = sanitize_key( wp_unslash( $_POST['hexa_fields_options_page'] ) );
        $page = self::$pages[ $slug ] ?? null;
        if ( null === $page || ! Renderer::verify() || ! current_user_can( $page['capability'] ) ) {
            return;
        }
        $context = Storage::context( $page['post_id'] );
        Form::save( FieldGroups::for_screen( [ 'options_page' => $slug ] ), $context, Renderer::posted() );
        Hooks::action( 'save_post', Storage::acf_id( $context ) );
        wp_safe_redirect( add_query_arg( 'updated', 'true', menu_page_url( $slug, false ) ?: admin_url( 'admin.php?page=' . $slug ) ) );
        exit;
    }

    /**
     * @param array<string,mixed> $page
     * @return array<string,mixed>
     */
    private static function normalize( array $page ): array {
        $page = array_merge(
            [
                'page_title' => 'Options', 'menu_title' => '', 'menu_slug' => '', 'capability' => 'edit_posts', 'parent_slug' => '',
                'position' => null, 'icon_url' => '', 'post_id' => 'options', 'update_button' => 'Update', 'updated_message' => 'Options Updated',
            ],
            $page
        );
        $page['menu_title'] = '' !== (string) $page['menu_title'] ? (string) $page['menu_title'] : (string) $page['page_title'];
        $page['menu_slug'] = '' !== (string) $page['menu_slug'] ? sanitize_key( (string) $page['menu_slug'] ) : 'acf-options-' . sanitize_title( (string) $page['menu_title'] );
        $page['post_id'] = 'option' === $page['post_id'] ? 'options' : (string) $page['post_id'];
        return $page;
    }
}
