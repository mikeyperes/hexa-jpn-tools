<?php

namespace Hexa\PluginCore\SnippetRegistry;

/**
 * Backward-compatible entry point for snippet registry rendering.
 *
 * The canonical UI now lives in SnippetsTableRenderer so every plugin can
 * share the same dense snippets structure without carrying its own copy.
 */
final class SnippetRenderer {
    public function render( SnippetRegistry|array $snippets, array $args = [] ): string {
        return ( new SnippetsTableRenderer() )->render( $snippets, $args );
    }
}
