<?php

namespace Hexa\PluginCore\SchemaTools;

/**
 * Each plugin declares the schema structures it owns: which pages each one
 * covers, how it is built, and the modes a site can choose from. Core keeps
 * the chosen mode of every structure, adds the enabled ones to the shared
 * SchemaPage graph, and gives every owner the same admin panel and preview.
 *
 * A structure ships working: its default mode applies until a site chooses
 * another one, so turning a plugin on is enough for its schema to print.
 *
 * Definition keys:
 * - id          stable key, unique within the owner
 * - label       short name shown in the admin
 * - where       the pages it covers, in plain words
 * - types       the schema.org types it prints, in plain words (single mode)
 * - modes       optional [mode => ['label' => ..., 'types' => ...]]; without
 *               it the structure has the single mode 'on'
 * - default     the mode used until a site chooses one ('off' ships it off)
 * - option      optional option name the owner already stores the mode in
 * - applies     callable(SchemaContext): bool
 * - build       callable(SchemaContext, string $mode): list<array> nodes
 * - sample      optional callable(): ?SchemaContext, a page to preview it on
 */
final class SchemaStructures {
    public const FILTER = 'hexa_plugin_core_schema_structures';
    public const OPTION = 'hexa_plugin_core_schema_structure_modes';
    public const OFF    = 'off';
    public const ON     = 'on';

    /** @var array<string,array<string,mixed>> */
    private static array $owners = [];

    /**
     * @param array{plugin:string,settings_url?:string,priority?:int,structures:list<array<string,mixed>>} $args
     */
    public static function register( string $owner, array $args ): void {
        $owner = sanitize_key( $owner );
        if ( '' === $owner ) {
            return;
        }
        $structures = [];
        foreach ( (array) ( $args['structures'] ?? [] ) as $definition ) {
            $definition = self::normalize( (array) $definition );
            if ( null !== $definition ) {
                $structures[ $definition['id'] ] = $definition;
            }
        }
        self::$owners[ $owner ] = [
            'owner'        => $owner,
            'plugin'       => (string) ( $args['plugin'] ?? $owner ),
            'settings_url' => (string) ( $args['settings_url'] ?? '' ),
            'priority'     => (int) ( $args['priority'] ?? 10 ),
            'structures'   => $structures,
        ];
        SchemaPage::register( $owner, static fn(): array => self::nodes( $owner, SchemaContext::current() ), (int) ( $args['priority'] ?? 10 ) );
        if ( ( function_exists( 'is_admin' ) && is_admin() ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
            ( new SchemaStructuresModule() )->register();
        }
    }

    /** @return array<string,array<string,mixed>> Every owner with its structures. */
    public static function all(): array {
        $owners = apply_filters( self::FILTER, self::$owners );
        return is_array( $owners ) ? $owners : self::$owners;
    }

    /** @return array<string,mixed>|null */
    public static function owner( string $owner ): ?array {
        return self::all()[ sanitize_key( $owner ) ] ?? null;
    }

    /** @return array<string,mixed>|null */
    public static function get( string $owner, string $id ): ?array {
        return self::owner( $owner )['structures'][ sanitize_key( $id ) ] ?? null;
    }

    /** The structure's current mode: the site's choice, else its default. */
    public static function mode( string $owner, string $id ): string {
        $definition = self::get( $owner, $id );
        if ( null === $definition ) {
            return self::OFF;
        }
        $saved = '' !== $definition['option']
            ? get_option( $definition['option'], '' )
            : ( (array) get_option( self::OPTION, [] ) )[ sanitize_key( $owner ) ][ $definition['id'] ] ?? '';
        $saved = is_string( $saved ) ? $saved : '';
        return self::valid_mode( $definition, $saved ) ? $saved : $definition['default'];
    }

    public static function enabled( string $owner, string $id ): bool {
        return self::OFF !== self::mode( $owner, $id );
    }

    /** Save a mode chosen in the admin. Returns false for an unknown structure or mode. */
    public static function set_mode( string $owner, string $id, string $mode ): bool {
        $owner = sanitize_key( $owner );
        $definition = self::get( $owner, $id );
        if ( null === $definition || ! self::valid_mode( $definition, $mode ) ) {
            return false;
        }
        if ( '' !== $definition['option'] ) {
            update_option( $definition['option'], $mode, false );
        } else {
            $modes = (array) get_option( self::OPTION, [] );
            $modes[ $owner ][ $definition['id'] ] = $mode;
            update_option( self::OPTION, $modes, false );
        }
        do_action( 'hexa_plugin_core_schema_structure_mode_changed', $owner, $definition['id'], $mode );
        return true;
    }

    /**
     * The owner's nodes for a page: every enabled structure that covers it.
     *
     * @return list<array<string,mixed>>
     */
    public static function nodes( string $owner, SchemaContext $context ): array {
        $nodes = [];
        foreach ( (array) ( self::owner( $owner )['structures'] ?? [] ) as $id => $definition ) {
            $mode = self::mode( $owner, (string) $id );
            if ( self::OFF === $mode || ! (bool) call_user_func( $definition['applies'], $context ) ) {
                continue;
            }
            foreach ( (array) call_user_func( $definition['build'], $context, $mode ) as $node ) {
                if ( is_array( $node ) && ! empty( $node['@type'] ) ) {
                    $nodes[] = $node;
                }
            }
        }
        return $nodes;
    }

    /**
     * The document an owner's structures give a page, merged as the page
     * prints it, without changing the current page's schema.
     *
     * @return array<string,mixed>
     */
    public static function document( string $owner, SchemaContext $context ): array {
        return SchemaPage::document_for( self::nodes( $owner, $context ) );
    }

    /**
     * One structure built on its sample page, as the merged document the
     * page would carry from this owner, with its validation issues.
     *
     * @return array{ok:bool,message:string,url:string,mode:string,document:array<string,mixed>,issues:list<array<string,string>>}
     */
    public static function preview( string $owner, string $id ): array {
        $definition = self::get( $owner, $id );
        $result = [ 'ok' => false, 'message' => '', 'url' => '', 'mode' => self::mode( $owner, $id ), 'document' => [], 'issues' => [] ];
        if ( null === $definition ) {
            $result['message'] = 'Unknown schema structure.';
            return $result;
        }
        $context = is_callable( $definition['sample'] ) ? call_user_func( $definition['sample'] ) : null;
        if ( ! $context instanceof SchemaContext ) {
            $result['message'] = 'There is no page for this structure to show on yet.';
            return $result;
        }
        $result['url'] = $context->url;
        if ( self::OFF === $result['mode'] ) {
            $result['message'] = 'This structure is off.';
            return $result;
        }
        $document = SchemaPage::document_for( (array) call_user_func( $definition['build'], $context, $result['mode'] ) );
        $result['document'] = $document;
        $result['issues']   = SchemaGraph::validation_issues( $document );
        $result['ok']       = ! empty( $document['@graph'] );
        $result['message']  = $result['ok'] ? 'Built from the current data.' : 'Nothing to print yet: the data this structure needs is empty.';
        return $result;
    }

    /** @return array<string,mixed>|null */
    private static function normalize( array $definition ): ?array {
        $id = sanitize_key( (string) ( $definition['id'] ?? '' ) );
        if ( '' === $id || ! is_callable( $definition['applies'] ?? null ) || ! is_callable( $definition['build'] ?? null ) ) {
            return null;
        }
        $modes = [];
        foreach ( (array) ( $definition['modes'] ?? [] ) as $mode => $meta ) {
            $mode = sanitize_key( (string) $mode );
            if ( '' !== $mode && self::OFF !== $mode ) {
                $modes[ $mode ] = [ 'label' => (string) ( $meta['label'] ?? $mode ), 'types' => (string) ( $meta['types'] ?? '' ) ];
            }
        }
        if ( [] === $modes ) {
            $modes[ self::ON ] = [ 'label' => 'On', 'types' => (string) ( $definition['types'] ?? '' ) ];
        }
        $default = sanitize_key( (string) ( $definition['default'] ?? array_key_first( $modes ) ) );
        if ( self::OFF !== $default && ! isset( $modes[ $default ] ) ) {
            $default = (string) array_key_first( $modes );
        }
        return [
            'id'      => $id,
            'label'   => (string) ( $definition['label'] ?? $id ),
            'where'   => (string) ( $definition['where'] ?? '' ),
            'modes'   => $modes,
            'default' => $default,
            'option'  => (string) ( $definition['option'] ?? '' ),
            'applies' => $definition['applies'],
            'build'   => $definition['build'],
            'sample'  => is_callable( $definition['sample'] ?? null ) ? $definition['sample'] : null,
        ];
    }

    private static function valid_mode( array $definition, string $mode ): bool {
        return self::OFF === $mode || isset( $definition['modes'][ $mode ] );
    }
}
