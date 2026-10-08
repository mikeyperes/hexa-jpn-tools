<?php

namespace Hexa\PluginCore\SearchQuery;

/**
 * Elementor text tag printing the current loop post's ResultTypeLabels label.
 * Loaded only from `elementor/dynamic_tags/register`, after Elementor defines Tag.
 */
final class ElementorResultTypeTag extends \Elementor\Core\DynamicTags\Tag {
    public function get_name(): string {
        return ResultTypeLabels::ELEMENTOR_TAG;
    }

    public function get_title(): string {
        return 'Result Type';
    }

    public function get_group(): string {
        return 'post';
    }

    public function get_categories(): array {
        return [ \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ];
    }

    public function render(): void {
        echo esc_html( ResultTypeLabels::label_for( (string) get_post_type() ) );
    }
}
