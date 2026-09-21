<?php

namespace Hexa\PluginCore\ShortcodeRegistry;

final class ShortcodeDefinition {
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $template,
        public readonly string $description,
        public readonly string $test_method,
        public readonly string $default_input = '',
        public readonly string $input_label = 'Input'
    ) {
    }

    public function shortcode( string $input = '' ): string {
        $value = $input !== '' ? $input : $this->default_input;

        return str_replace( '{input}', $value, $this->template );
    }
}

