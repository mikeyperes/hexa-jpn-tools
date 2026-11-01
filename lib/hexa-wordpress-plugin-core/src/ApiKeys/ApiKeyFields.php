<?php

declare( strict_types=1 );

namespace Hexa\PluginCore\ApiKeys;

use Hexa\PluginCore\CredentialVault\CredentialStore;
use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;
use Hexa\PluginCore\WpAdminComponents\CoreUi;

/**
 * API key fields for any plugin: save, remove and test a key, stored
 * encrypted through the credential store. A plugin registers its fields once
 * (with a verifier to get a Test button) and renders them on its screen.
 */
final class ApiKeyFields {
    public const NONCE_ACTION = 'hexa_core_api_keys';

    /** @var array<string,ApiKeyField> */
    private static array $fields = [];
    private static bool $ajax = false;
    private static bool $script = false;

    public static function register( ApiKeyField $field ): void {
        self::$fields[ $field->id ] = $field;
        if ( ! self::$ajax ) {
            self::$ajax = true;
            AjaxActionRegistry::create( [ 'action_prefix' => 'hexa_core', 'nonce_action' => self::NONCE_ACTION, 'nonce_field' => 'nonce' ] )
                ->register( [
                    'api_key_save'   => [ 'callback' => [ self::class, 'handle_save' ] ],
                    'api_key_delete' => [ 'callback' => [ self::class, 'handle_delete' ] ],
                    'api_key_test'   => [ 'callback' => [ self::class, 'handle_test' ] ],
                ] );
        }
    }

    public static function get( string $id ): ?ApiKeyField {
        return self::$fields[ $id ] ?? null;
    }

    public static function value( string $id ): string {
        $field = self::get( $id );

        return $field ? (string) ( new CredentialStore() )->get( $field->slug, $field->key_name ) : '';
    }

    public static function test( string $id, ?string $key = null ): ApiKeyCheck {
        $field = self::get( $id );
        if ( ! $field || ! $field->verifier ) {
            return ApiKeyCheck::error( 'This key has no test.' );
        }

        return $field->verifier->verify( null === $key ? self::value( $id ) : $key );
    }

    public static function render( string $id ): string {
        $field = self::get( $id );
        if ( ! $field ) {
            return '';
        }
        $store  = new CredentialStore();
        $masked = $store->get_masked( $field->slug, $field->key_name );
        $saved  = $store->exists( $field->slug, $field->key_name );
        $steps  = '';
        foreach ( $field->steps as $step ) {
            $steps .= '<li>' . wp_kses_post( $step ) . '</li>';
        }
        $buttons = '<button type="button" class="hpc-button" data-hpc-key-action="save">Save key</button>'
            . '<button type="button" class="hpc-button secondary" data-hpc-key-action="delete"' . ( $saved ? '' : ' disabled' ) . '>Remove</button>'
            . ( $field->verifier ? '<button type="button" class="hpc-button secondary" data-hpc-key-action="test"' . ( $saved ? '' : ' disabled' ) . '>Test key</button>' : '' );

        return '<div class="hpc-api-key" data-hpc-key="' . esc_attr( $field->id ) . '" data-ajax-url="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" data-nonce="' . esc_attr( wp_create_nonce( self::NONCE_ACTION ) ) . '">'
            . '<p><strong>' . esc_html( $field->label ) . '</strong> ' . ( $saved ? CoreUi::pill( 'Saved: ' . $masked, 'success' ) : CoreUi::pill( 'Not saved', 'warning' ) ) . '</p>'
            . '<p><input type="password" class="regular-text" autocomplete="off" placeholder="' . esc_attr( $field->placeholder ) . '" aria-label="' . esc_attr( $field->label ) . '"></p>'
            . '<p class="hpc-actions">' . $buttons . '</p>'
            . '<p class="hpc-api-key-status" aria-live="polite"></p>'
            . ( '' !== $steps ? '<div class="hpc-callout"><strong>How to get it</strong><ol class="hpc-steps">' . $steps . '</ol>'
                . ( '' !== $field->help_url ? '<p>' . CoreUi::external_link( $field->help_url, $field->help_label ) . '</p>' : '' ) . '</div>' : '' )
            . '</div>' . self::script();
    }

    /** @return array<string,mixed> */
    public static function handle_save( AjaxRequest $request ): array {
        $field = self::get( $request->key( 'field', '', 'post' ) );
        $value = trim( (string) wp_unslash( (string) $request->raw( 'value', '', 'post' ) ) );
        if ( ! $field || '' === $value ) {
            return [ 'saved' => false, 'message' => $field ? 'Paste a key first.' : 'Unknown key.' ];
        }
        $check = $field->verifier ? $field->verifier->verify( $value ) : null;
        if ( $check && ApiKeyCheck::INVALID === $check->status ) {
            return [ 'saved' => false, 'message' => 'Not saved: ' . $check->message, 'check' => $check->to_array() ];
        }
        $store = new CredentialStore();
        $saved = $store->store( $field->slug, $field->key_name, $value );

        return [ 'saved' => $saved, 'masked' => $store->get_masked( $field->slug, $field->key_name ), 'message' => $saved ? 'Saved.' . ( $check ? ' ' . $check->message : '' ) : 'Not saved.', 'check' => $check ? $check->to_array() : null ];
    }

    /** @return array<string,mixed> */
    public static function handle_delete( AjaxRequest $request ): array {
        $field = self::get( $request->key( 'field', '', 'post' ) );
        $done  = $field && ( new CredentialStore() )->delete( $field->slug, $field->key_name );

        return [ 'saved' => $done, 'message' => $done ? 'Removed.' : 'Nothing to remove.' ];
    }

    /** @return array<string,mixed> */
    public static function handle_test( AjaxRequest $request ): array {
        $check = self::test( $request->key( 'field', '', 'post' ) );

        return [ 'saved' => $check->ok(), 'message' => $check->message, 'check' => $check->to_array() ];
    }

    private static function script(): string {
        if ( self::$script ) {
            return '';
        }
        self::$script = true;

        return <<<'HTML'
<script>
(function () {
  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-hpc-key-action]');
    if (!button) return;
    var box = button.closest('.hpc-api-key'), action = button.getAttribute('data-hpc-key-action');
    var status = box.querySelector('.hpc-api-key-status'), input = box.querySelector('input');
    var data = new FormData();
    data.append('action', 'hexa_core_api_key_' + action);
    data.append('nonce', box.getAttribute('data-nonce'));
    data.append('field', box.getAttribute('data-hpc-key'));
    if (action === 'save') data.append('value', input.value);
    button.disabled = true;
    status.textContent = action === 'test' ? 'Testing...' : 'Working...';
    fetch(box.getAttribute('data-ajax-url'), { method: 'POST', credentials: 'same-origin', body: data })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        var d = json && json.data ? json.data : {};
        status.textContent = (d.saved ? '✅ ' : '❌ ') + (d.message || 'Failed.');
        if (action === 'save' && d.saved) { input.value = ''; box.querySelectorAll('[data-hpc-key-action]').forEach(function (b) { b.disabled = false; }); }
        if (action === 'delete' && d.saved) { box.querySelectorAll('[data-hpc-key-action="delete"],[data-hpc-key-action="test"]').forEach(function (b) { b.disabled = true; }); }
      })
      .catch(function () { status.textContent = '❌ The request failed.'; })
      .finally(function () { if (action === 'save' || action === 'test') button.disabled = false; });
  });
})();
</script>
HTML;
    }
}
