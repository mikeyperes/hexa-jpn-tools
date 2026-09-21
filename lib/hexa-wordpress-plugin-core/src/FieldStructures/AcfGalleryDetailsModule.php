<?php

declare( strict_types=1 );

namespace Hexa\PluginCore\FieldStructures;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\WpAdminComponents\MediaGalleryDetailsRenderer;

/**
 * Binds the generic media-gallery inspector to one host-owned ACF gallery field.
 */
final class AcfGalleryDetailsModule implements ModuleInterface {
    /** @var array<string,mixed> */
    private array $config;

    /** @param array<string,mixed> $config */
    public function __construct( array $config ) {
        $field_key = sanitize_key( (string) ( $config['field_key'] ?? '' ) );
        if ( '' === $field_key ) {
            throw new \InvalidArgumentException( 'ACF gallery details require a field_key.' );
        }

        $action = sanitize_key( (string) ( $config['ajax_action'] ?? '' ) );
        if ( '' === $action ) {
            $action = 'hexa_acf_gallery_' . substr( md5( $field_key ), 0, 12 );
        }

        $this->config = array_merge(
            [
                'field_key'         => $field_key,
                'title'             => 'Details',
                'persist_key'       => 'acf-gallery-details-' . $field_key,
                'open'              => false,
                'preview_pixels'    => 112,
                'preview_image_size'=> 'medium',
                'allow_remove'      => true,
                'live_refresh'      => true,
                'ajax_action'       => $action,
                'nonce_field'       => 'nonce',
                'capability'        => '',
                'context'           => null,
                'remove_confirm'    => 'Remove this image from the gallery? The Media Library attachment will remain available.',
            ],
            $config,
            [
                'field_key'   => $field_key,
                'ajax_action' => $action,
            ]
        );
    }

    public function register(): void {
        add_action( 'acf/render_field/key=' . $this->field_key(), [ $this, 'render' ] );
        add_action( 'wp_ajax_' . $this->ajax_action(), [ $this, 'handle_ajax' ] );
    }

    /** @param array<string,mixed> $field */
    public function render( array $field ): void {
        $value = $field['value'] ?? [];
        if ( ! is_array( $value ) ) {
            $value = [];
        }

        $context      = $this->resolve_context( $field );
        $allow_remove = '' !== $context && ! empty( $this->config['allow_remove'] ) && $this->can_edit_context( $context );

        echo MediaGalleryDetailsRenderer::render(
            $value,
            $this->renderer_args( $context, $allow_remove )
        ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function handle_ajax(): void {
        $context   = $this->request_context();
        $field_key = isset( $_POST['field_key'] ) ? sanitize_key( (string) wp_unslash( $_POST['field_key'] ) ) : '';
        if ( '' === $context || $field_key !== $this->field_key() ) {
            wp_send_json_error( [ 'message' => 'Invalid gallery field context.' ], 400 );
        }
        if ( ! $this->can_edit_context( $context ) ) {
            wp_send_json_error( [ 'message' => 'You do not have permission to edit this gallery.' ], 403 );
        }

        check_ajax_referer( $this->nonce_action( $context ), (string) $this->config['nonce_field'] );
        $operation = isset( $_POST['operation'] ) ? sanitize_key( (string) wp_unslash( $_POST['operation'] ) ) : '';

        if ( 'refresh' === $operation ) {
            $raw_ids = isset( $_POST['attachment_ids'] ) ? (array) wp_unslash( $_POST['attachment_ids'] ) : [];
            $ids     = MediaGalleryDetailsRenderer::attachment_ids( $raw_ids );
            wp_send_json_success(
                [
                    'content_html' => MediaGalleryDetailsRenderer::render_content(
                        $ids,
                        $this->renderer_args( $context, ! empty( $this->config['allow_remove'] ) )
                    ),
                    'count'        => count( $ids ),
                ]
            );
        }

        if ( 'remove' !== $operation ) {
            wp_send_json_error( [ 'message' => 'Unknown gallery operation.' ], 400 );
        }

        $attachment_id = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;
        $result        = $this->remove_attachment( $attachment_id, $context );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ], 400 );
        }

        wp_send_json_success( $result );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function remove_attachment( int $attachment_id, string $context ): array|\WP_Error {
        $context = $this->normalize_context( $context );
        if ( $attachment_id < 1 || '' === $context ) {
            return new \WP_Error( 'hexa_acf_gallery_invalid_removal', 'A valid gallery image and context are required.' );
        }
        if ( ! function_exists( 'get_field' ) || ! function_exists( 'update_field' ) ) {
            return new \WP_Error( 'hexa_acf_gallery_acf_unavailable', 'Advanced Custom Fields is unavailable.' );
        }

        $value = get_field( $this->field_key(), $context, false );
        $ids   = MediaGalleryDetailsRenderer::attachment_ids( is_array( $value ) ? $value : [] );
        if ( ! in_array( $attachment_id, $ids, true ) ) {
            return [
                'attachment_id' => $attachment_id,
                'remaining_ids' => $ids,
                'count'         => count( $ids ),
                'changed'       => false,
                'message'       => 'Image removed from the unsaved gallery selection.',
            ];
        }

        $remaining = array_values( array_filter( $ids, static fn( int $id ): bool => $id !== $attachment_id ) );
        if ( false === update_field( $this->field_key(), $remaining, $context ) ) {
            return new \WP_Error( 'hexa_acf_gallery_update_failed', 'The gallery value could not be updated.' );
        }

        return [
            'attachment_id' => $attachment_id,
            'remaining_ids' => $remaining,
            'count'         => count( $remaining ),
            'changed'       => true,
            'message'       => 'Image removed from the gallery. The Media Library attachment was not deleted.',
        ];
    }

    public function field_key(): string {
        return (string) $this->config['field_key'];
    }

    public function ajax_action(): string {
        return (string) $this->config['ajax_action'];
    }

    /** @return array<string,mixed> */
    public function config(): array {
        return $this->config;
    }

    /** @param array<string,mixed> $field */
    private function resolve_context( array $field ): string {
        $resolver = $this->config['context'] ?? null;
        if ( is_callable( $resolver ) ) {
            return $this->normalize_context( (string) call_user_func( $resolver, $field, $this ) );
        }
        if ( is_string( $resolver ) && '' !== $resolver ) {
            return $this->normalize_context( $resolver );
        }
        if ( function_exists( 'acf_get_form_data' ) ) {
            $context = $this->normalize_context( (string) acf_get_form_data( 'post_id' ) );
            if ( '' !== $context ) {
                return $context;
            }
        }

        $user_id = isset( $GLOBALS['user_id'] ) ? absint( $GLOBALS['user_id'] ) : 0;
        if ( $user_id < 1 && isset( $_GET['user_id'] ) ) {
            $user_id = absint( wp_unslash( $_GET['user_id'] ) );
        }
        if ( $user_id < 1 && isset( $GLOBALS['profileuser']->ID ) ) {
            $user_id = absint( $GLOBALS['profileuser']->ID );
        }
        if ( $user_id > 0 ) {
            return 'user_' . $user_id;
        }
        if ( isset( $GLOBALS['post']->ID ) ) {
            return (string) absint( $GLOBALS['post']->ID );
        }

        return '';
    }

    private function request_context(): string {
        return isset( $_POST['context'] )
            ? $this->normalize_context( (string) wp_unslash( $_POST['context'] ) )
            : '';
    }

    private function normalize_context( string $context ): string {
        $context = trim( $context );

        return preg_match( '/\A[a-zA-Z0-9_:\-]{1,190}\z/', $context ) ? $context : '';
    }

    private function can_edit_context( string $context ): bool {
        $capability = $this->config['capability'] ?? '';
        if ( is_callable( $capability ) ) {
            return (bool) call_user_func( $capability, $context, $this );
        }
        if ( is_string( $capability ) && '' !== $capability ) {
            return current_user_can( $capability );
        }
        if ( preg_match( '/\Auser_(\d+)\z/', $context, $matches ) ) {
            return current_user_can( 'edit_user', (int) $matches[1] );
        }
        if ( preg_match( '/\Aterm_(\d+)\z/', $context, $matches ) ) {
            return current_user_can( 'edit_term', (int) $matches[1] );
        }
        if ( preg_match( '/\Acomment_(\d+)\z/', $context, $matches ) ) {
            return current_user_can( 'edit_comment', (int) $matches[1] );
        }
        if ( ctype_digit( $context ) || preg_match( '/\Apost_(\d+)\z/', $context, $matches ) ) {
            $post_id = ctype_digit( $context ) ? (int) $context : (int) $matches[1];
            return current_user_can( 'edit_post', $post_id );
        }

        return current_user_can( 'manage_options' );
    }

    /** @return array<string,mixed> */
    private function renderer_args( string $context, bool $allow_remove ): array {
        $interactive = '' !== $context && $this->can_edit_context( $context );

        return [
            'title'               => (string) $this->config['title'],
            'persist_key'         => (string) $this->config['persist_key'],
            'open'                => ! empty( $this->config['open'] ),
            'preview_pixels'      => (int) $this->config['preview_pixels'],
            'preview_image_size'  => (string) $this->config['preview_image_size'],
            'allow_remove'        => $allow_remove,
            'live_refresh'        => $interactive && ! empty( $this->config['live_refresh'] ),
            'ajax_url'            => $interactive ? admin_url( 'admin-ajax.php' ) : '',
            'ajax_action'         => $interactive ? $this->ajax_action() : '',
            'nonce_field'         => (string) $this->config['nonce_field'],
            'nonce'               => $interactive ? wp_create_nonce( $this->nonce_action( $context ) ) : '',
            'context'             => $context,
            'field_key'           => $this->field_key(),
            'remove_confirm'      => (string) $this->config['remove_confirm'],
        ];
    }

    private function nonce_action( string $context ): string {
        return $this->ajax_action() . '|' . $this->field_key() . '|' . $context;
    }
}
