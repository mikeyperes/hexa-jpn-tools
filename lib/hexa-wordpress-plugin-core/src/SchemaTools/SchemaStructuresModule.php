<?php

namespace Hexa\PluginCore\SchemaTools;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
use Hexa\PluginCore\WpAdminAjax\AjaxFailure;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;

/**
 * The admin and command-line actions every schema structure shares: list the
 * structures, change a structure's mode and preview it on its sample page.
 * Registered once however many plugins add it.
 *
 *   wp hexa schema list [--owner=<owner>]
 *   wp hexa schema preview --owner=<owner> --id=<id>
 *   wp hexa schema set --owner=<owner> --id=<id> --mode=<mode|off>
 */
final class SchemaStructuresModule implements ModuleInterface {
    public const NONCE  = 'hexa_schema_structures';
    public const PREFIX = 'hexa_schema_structures';

    private static bool $registered = false;

    public function register(): void {
        if ( self::$registered ) {
            return;
        }
        self::$registered = true;

        AjaxActionRegistry::create( [ 'nonce_action' => self::NONCE, 'action_prefix' => self::PREFIX ] )->register(
            [
                'list'    => static fn( AjaxRequest $request ): array => self::rows( $request->key( 'owner', '', 'post' ) ),
                'save'    => [ self::class, 'save' ],
                'preview' => static fn( AjaxRequest $request ): array => SchemaStructures::preview( $request->key( 'owner', '', 'post' ), $request->key( 'id', '', 'post' ) ),
            ]
        );

        if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\WP_CLI' ) ) {
            \WP_CLI::add_command( 'hexa schema', [ self::class, 'cli' ] );
        }
    }

    /** @return array<string,mixed> */
    public static function save( AjaxRequest $request ): array {
        $owner = $request->key( 'owner', '', 'post' );
        $id    = $request->key( 'id', '', 'post' );
        $mode  = $request->key( 'mode', '', 'post' );
        if ( ! SchemaStructures::set_mode( $owner, $id, $mode ) ) {
            throw new AjaxFailure( 'That schema structure or mode does not exist.', 400, 'invalid_structure' );
        }
        return [ 'message' => SchemaStructures::OFF === $mode ? 'Turned off.' : 'Saved.', 'rows' => self::rows( $owner ) ];
    }

    /**
     * Every structure of an owner (or of every owner) with its current mode.
     *
     * @return list<array<string,mixed>>
     */
    public static function rows( string $owner = '' ): array {
        $rows = [];
        foreach ( SchemaStructures::all() as $slug => $data ) {
            if ( '' !== $owner && $owner !== $slug ) {
                continue;
            }
            foreach ( (array) $data['structures'] as $id => $definition ) {
                $sample = is_callable( $definition['sample'] ) ? call_user_func( $definition['sample'] ) : null;
                $rows[] = [
                    'owner'        => (string) $slug,
                    'plugin'       => (string) $data['plugin'],
                    'settings_url' => (string) $data['settings_url'],
                    'id'           => (string) $id,
                    'label'        => (string) $definition['label'],
                    'where'        => (string) $definition['where'],
                    'modes'        => $definition['modes'],
                    'default'      => (string) $definition['default'],
                    'mode'         => SchemaStructures::mode( (string) $slug, (string) $id ),
                    'sample_url'   => $sample instanceof SchemaContext ? $sample->url : '',
                ];
            }
        }
        return $rows;
    }

    /**
     * @param list<string>         $args
     * @param array<string,string> $assoc
     */
    public static function cli( array $args, array $assoc ): void {
        $verb  = $args[0] ?? 'list';
        $owner = sanitize_key( (string) ( $assoc['owner'] ?? '' ) );
        $id    = sanitize_key( (string) ( $assoc['id'] ?? '' ) );
        if ( 'list' === $verb ) {
            $result = self::rows( $owner );
        } elseif ( 'preview' === $verb ) {
            $result = SchemaStructures::preview( $owner, $id );
        } elseif ( 'set' === $verb && isset( $assoc['mode'] ) ) {
            $result = [ 'ok' => SchemaStructures::set_mode( $owner, $id, sanitize_key( (string) $assoc['mode'] ) ), 'mode' => SchemaStructures::mode( $owner, $id ) ];
        } else {
            \WP_CLI::error( 'Use list [--owner], preview --owner --id, or set --owner --id --mode.' );
            return;
        }
        \WP_CLI::line( (string) wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
    }
}
