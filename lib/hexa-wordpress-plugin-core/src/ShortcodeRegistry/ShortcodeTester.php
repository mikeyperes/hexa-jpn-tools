<?php

namespace Hexa\PluginCore\ShortcodeRegistry;

final class ShortcodeTester {
    /**
     * This skeleton delegates rendering to the host plugin because shortcode
     * execution depends on the active WordPress install.
     */
    public function prepare( ShortcodeDefinition $definition, string $input = '' ): ShortcodeTestResult {
        return new ShortcodeTestResult(
            $definition->shortcode( $input ),
            'prepared',
            '',
            true
        );
    }
}

