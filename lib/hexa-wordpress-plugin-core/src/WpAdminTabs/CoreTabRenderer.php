<?php

namespace Hexa\PluginCore\WpAdminTabs;

use Hexa\PluginCore\ActivityLog\ActivityLogConfig;
use Hexa\PluginCore\ActivityLog\ActivityLogEntry;
use Hexa\PluginCore\ActivityLog\ActivityLogger;
use Hexa\PluginCore\ActivityLog\ActivityLogRenderer;
use Hexa\PluginCore\CredentialVault\CredentialFieldRenderer;
use Hexa\PluginCore\SmartSearch\SmartSearchRenderer;
use Hexa\PluginCore\FieldStructures\FieldStructureRenderer;
use Hexa\PluginCore\BrandColors\BrandColorProvider;
use Hexa\PluginCore\CoreRuntime\CoreVersion;
use Hexa\PluginCore\CoreRuntime\CorePackageRuntime;
use Hexa\PluginCore\WpAdminComponents\ColorControl;
use Hexa\PluginCore\WpAdminComponents\CoreUi;
use Hexa\PluginCore\WpAdminComponents\DetailedColorPicker;

final class CoreTabRenderer {
    private CoreTabConfig $config;

    public function __construct( CoreTabConfig $config ) {
        $this->config = $config;
    }

    public function render(): void {
        CoreUi::render_assets();

        $core_version = CoreVersion::current( $this->config->core_root() );
        ?>
        <div class="hpc-ui">
            <div class="hpc-shell" id="hexa-plugin-core-workspace">
                <section class="hpc-hero">
                    <div>
                        <h2>Hexa WordPress Plugin Core</h2>
                        <p>Shared WordPress plugin library for consistent tabs, UI components, activity logs, API key storage, smart search, updater panels, shortcode tooling, and error-log monitors.</p>
                    </div>
                    <div class="hpc-actions" style="align-content:start;justify-content:flex-end;">
                        <?php echo CoreUi::pill( 'Core v' . $core_version, 'dark' ); ?>
                        <?php echo CoreUi::pill( 'hexa/plugin-core', 'success' ); ?>
                    </div>
                </section>

                <nav class="hpc-core-tabs" aria-label="Hexa core sections">
                    <button type="button" class="hpc-core-tab active" data-hpc-core-tab="readme">README</button>
                    <button type="button" class="hpc-core-tab" data-hpc-core-tab="ui">UI Elements</button>
                    <button type="button" class="hpc-core-tab" data-hpc-core-tab="brand-colors">Brand Colors</button>
                    <button type="button" class="hpc-core-tab" data-hpc-core-tab="activity">Activity Log</button>
                    <button type="button" class="hpc-core-tab" data-hpc-core-tab="search">Smart Search / X-Search</button>
                    <button type="button" class="hpc-core-tab" data-hpc-core-tab="api-keys">API Keys</button>
                    <button type="button" class="hpc-core-tab" data-hpc-core-tab="logs">Error Logs</button>
                    <button type="button" class="hpc-core-tab" data-hpc-core-tab="field-structures">Field Structures</button>
                </nav>

                <section class="hpc-core-pane active" data-hpc-core-pane="readme">
                    <?php echo $this->render_readme_section(); ?>
                </section>

                <section class="hpc-core-pane" data-hpc-core-pane="ui">
                    <?php echo $this->render_ui_elements_section(); ?>
                </section>

                <section class="hpc-core-pane" data-hpc-core-pane="brand-colors">
                    <?php echo $this->render_brand_colors_section(); ?>
                </section>

                <section class="hpc-core-pane" data-hpc-core-pane="activity">
                    <?php $this->render_activity_section(); ?>
                </section>

                <section class="hpc-core-pane" data-hpc-core-pane="search">
                    <?php $this->render_search_section(); ?>
                </section>

                <section class="hpc-core-pane" data-hpc-core-pane="api-keys">
                    <?php echo $this->render_api_key_section(); ?>
                </section>

                <section class="hpc-core-pane" data-hpc-core-pane="logs">
                    <?php echo $this->render_error_logs_section(); ?>
                </section>
                <section class="hpc-core-pane" data-hpc-core-pane="field-structures">
                    <?php echo $this->render_field_structures_section(); ?>
                </section>
            </div>
        </div>
        <script>
        (function(){
            var root = document.getElementById('hexa-plugin-core-workspace');
            if (!root) return;
            var tabs = root.querySelectorAll('[data-hpc-core-tab]');
            var panes = root.querySelectorAll('[data-hpc-core-pane]');
            tabs.forEach(function(tab){
                tab.addEventListener('click', function(){
                    var target = tab.getAttribute('data-hpc-core-tab');
                    tabs.forEach(function(item){ item.classList.remove('active'); });
                    panes.forEach(function(item){ item.classList.remove('active'); });
                    tab.classList.add('active');
                    var pane = root.querySelector('[data-hpc-core-pane="' + target + '"]');
                    if (pane) pane.classList.add('active');
                });
            });
        })();
        </script>
        <?php
    }

    private function render_readme_section(): string {
        $guide = <<<'README'
# Hexa WordPress Plugin Core

Core owns shared structure. Host plugins pass plugin-specific values such as slugs, hook names, paths, capabilities, labels, and repositories.

Required rule:
- Build reusable UI and behavior in Hexa Plugin Core first.
- Host plugins should call core classes with input arrays/config objects.
- Do not duplicate panels, tabs, credential fields, smart search, updater UI, shortcode displays, or log viewers inside individual plugins.

Current core sections:
- Tabs
- UI
- Brand Colors
- Activity
- Search
- Credentials
- Logs
- Updater
- Shortcodes
- Field Structures
README;

        $runtime          = CorePackageRuntime::report();
        $selected         = is_array( $runtime['selected'] ?? null ) ? $runtime['selected'] : [];
        $candidate_count  = count( (array) ( $runtime['candidates'] ?? [] ) );
        $issues           = (array) ( $runtime['issues'] ?? [] );
        $runtime_status   = ! empty( $runtime['healthy'] ) ? CoreUi::pill( 'Healthy', 'success' ) : CoreUi::pill( 'Needs attention', 'danger' );
        $runtime_details  = '<p>' . $runtime_status . '</p>'
            . '<p>Selected host: <span class="hpc-code">' . esc_html( (string) ( $selected['host'] ?? 'unresolved' ) ) . '</span></p>'
            . '<p>Selected version: <span class="hpc-code">' . esc_html( (string) ( $selected['version'] ?? 'unresolved' ) ) . '</span></p>'
            . '<p>Registered candidates: <span class="hpc-code">' . esc_html( (string) $candidate_count ) . '</span></p>';

        if ( [] !== $issues ) {
            $runtime_details .= '<ul>';
            foreach ( $issues as $issue ) {
                $runtime_details .= '<li>' . esc_html( (string) ( $issue['message'] ?? $issue['type'] ?? 'Unknown Core runtime issue.' ) ) . '</li>';
            }
            $runtime_details .= '</ul>';
        }

        return '<div class="hpc-grid">'
            . CoreUi::card(
                [
                    'title'     => 'Runtime package owner',
                    'body_html' => $runtime_details,
                    'meta_html' => CoreUi::pill( 'One namespace owner', ! empty( $runtime['healthy'] ) ? 'success' : 'danger' ),
                ]
            )
            . CoreUi::card(
                [
                    'title'     => 'Source of truth',
                    'body_html' => '<p>Reusable plugin behavior belongs in <span class="hpc-code">Hexa\\PluginCore\\</span>. HWS is now a host plugin that passes configuration into core.</p>',
                ]
            )
            . CoreUi::card(
                [
                    'title'     => 'README files',
                    'body_html' => '<p>Main README: <span class="hpc-path">' . esc_html( $this->config->readme_path() ) . '</span></p>'
                        . '<p>Agent guide: <span class="hpc-path">' . esc_html( $this->config->library_path() ) . '</span></p>',
                    'meta_html' => CoreUi::copy_button( $this->config->readme_path(), 'Copy README path' ),
                ]
            )
            . CoreUi::card(
                [
                    'title'     => 'Implementation rule',
                    'body_html' => '<p>Every new reusable element needs an example, a visual, and a clear API. This tab is the live component catalog.</p>',
                ]
            )
            . '</div><div style="height:14px"></div><pre class="hpc-readme">' . esc_html( $guide ) . '</pre>';
    }

    private function render_ui_elements_section(): string {
        $collapsible_body = '<p>This expandable block is generated by <span class="hpc-code">CoreUi::collapsible()</span>. Use it for advanced settings, setup instructions, readme sections, and logs.</p>';

        return '<div class="hpc-grid two">'
            . CoreUi::card(
                [
                    'title'     => 'Cards and subcards',
                    'body_html' => '<p>Main surface from <span class="hpc-code">CoreUi::card()</span>.</p>'
                        . CoreUi::subcard(
                            [
                                'title'     => 'Nested detail',
                                'body_html' => '<p>Compact detail from <span class="hpc-code">CoreUi::subcard()</span>. Keep repeated settings and examples in subcards.</p>',
                            ]
                        ),
                ]
            )
            . CoreUi::card(
                [
                    'title'     => 'Status pills and tooltips',
                    'body_html' => '<p>' . CoreUi::pill( 'Healthy', 'success' ) . ' ' . CoreUi::pill( 'Warning', 'warning' ) . ' ' . CoreUi::pill( 'Danger', 'danger' ) . ' ' . CoreUi::pill( 'Dark', 'dark' ) . '</p>'
                        . '<p>Tooltip example: ' . CoreUi::tooltip( 'Tooltips come from core, not one-off plugin CSS.' ) . '</p>',
                ]
            )
            . CoreUi::card(
                [
                    'title'     => 'Buttons and copy actions',
                    'body_html' => '<div class="hpc-actions"><button type="button" class="hpc-button">Primary</button><button type="button" class="hpc-button secondary">Secondary</button><button type="button" class="hpc-button danger">Danger</button>' . CoreUi::copy_button( '[site_logo key="logo"]', 'Copy shortcode' ) . '</div>',
                ]
            )
            . CoreUi::card(
                [
                    'title'     => 'Collapsible component',
                    'body_html' => CoreUi::collapsible(
                        [
                            'title'     => 'Expandable example',
                            'open'      => true,
                            'meta_html' => CoreUi::pill( 'Core UI', 'success' ),
                            'body_html' => $collapsible_body,
                        ]
                    ),
                ]
            )
            . '</div>';
    }

    private function render_brand_colors_section(): string {
        $payload = BrandColorProvider::payload( '#2d5277' );
        $example = DetailedColorPicker::render(
            [
                'id'          => 'hpc-demo-detailed-color-picker',
                'title'       => 'Detailed Color Picker',
                'description' => 'Primary and secondary colors use the same picker, editable hex, RGB, swatch, copy, and Elementor import behavior.',
                'primary'     => [
                    'key'             => 'example_primary_color',
                    'label'           => 'Primary color',
                    'value'           => (string) $payload['primary_color'],
                    'default'         => '#2d5277',
                    'hex_input_class' => 'hpc-demo-primary-color',
                ],
                'secondary'   => [
                    'key'             => 'example_secondary_color',
                    'label'           => 'Secondary color',
                    'value'           => (string) $payload['secondary_color'],
                    'default'         => '#111827',
                    'hex_input_class' => 'hpc-demo-secondary-color',
                ],
                'show_fonts'  => true,
                'fonts'       => [
                    [
                        'key'   => 'primary_font_family',
                        'token' => 'primary_font_family',
                        'label' => 'Primary font family',
                        'value' => '',
                    ],
                    [
                        'key'   => 'secondary_font_family',
                        'token' => 'secondary_font_family',
                        'label' => 'Secondary font family',
                        'value' => '',
                    ],
                ],
            ]
        );
        $code = <<<'CODE'
use Hexa\PluginCore\BrandColors\BrandColorProvider;
use Hexa\PluginCore\WpAdminComponents\DetailedColorPicker;

$brand = BrandColorProvider::payload('#2d5277');

echo DetailedColorPicker::render([
    'title' => 'Brand card colors',
    'primary' => [
        'key' => 'primary_color',
        'value' => $settings['primary_color'] ?? $brand['primary_color'],
        'hex_input_class' => 'plugin-primary-color',
    ],
    'secondary' => [
        'key' => 'secondary_color',
        'value' => $settings['secondary_color'] ?? $brand['secondary_color'],
        'hex_input_class' => 'plugin-secondary-color',
    ],
    'show_elementor_import' => true,
    'show_fonts' => false,
]);
CODE;

        return '<div class="hpc-grid two">'
            . CoreUi::card(
                [
                    'title'     => 'HWS Brand Assets source',
                    'body_html' => '<p>Primary color: <span class="hpc-code">' . esc_html( (string) $payload['primary_color'] ) . '</span></p><p>RGB: <span class="hpc-code">' . esc_html( (string) $payload['primary_rgb'] ) . '</span></p>'
                        . ( '' !== (string) $payload['admin_url'] ? '<p>' . CoreUi::external_link( (string) $payload['admin_url'], 'Open HWS Brand Assets' ) . '</p>' : '' ),
                ]
            )
            . CoreUi::card(
                [
                    'title'     => 'Host plugin contract',
                    'body_html' => '<p>Core owns the detailed color/font control structure and Elementor token parsing. Host plugins own persistence and pass plugin-specific setting keys/classes.</p>',
                ]
            )
            . '</div><div style="height:14px"></div>'
            . CoreUi::card(
                [
                    'title'     => 'Visual example',
                    'body_html' => $example,
                ]
            )
            . '<div style="height:14px"></div>'
            . CoreUi::card(
                [
                    'title'     => 'Usage example',
                    'body_html' => '<pre class="hpc-readme">' . esc_html( $code ) . '</pre>',
                ]
            );
    }

    private function render_activity_section(): void {
        echo '<div class="hpc-grid">'
            . CoreUi::card( [ 'title' => 'Page-only', 'body_html' => '<p>Render-only entries. Removed when the page refreshes.</p>' ] )
            . CoreUi::card( [ 'title' => 'Transient', 'body_html' => '<p>Stored in a WordPress transient with a TTL.</p>' ] )
            . CoreUi::card( [ 'title' => 'Permanent', 'body_html' => '<p>Stored in a WordPress option until cleared.</p>' ] )
            . '</div><div style="height:14px"></div>';

        $config = new ActivityLogConfig(
            [
                'id'          => 'hexa-core-activity-demo',
                'title'       => 'Core Activity Monitor Demo',
                'storage'     => ActivityLogConfig::STORAGE_PAGE,
                'storage_key' => 'hexa_core_activity_demo',
                'collapsed'   => true,
                'max_entries' => 50,
            ]
        );

        $logger = new ActivityLogger( $config );
        $logger->add( new ActivityLogEntry( 'Core internal tabs rendered.', [ 'tab' => 'activity' ], 'system', 'tabs', null, 'success' ) );
        $logger->add( new ActivityLogEntry( 'UI catalog loaded from shared primitives.', [ 'component' => 'CoreUi' ], 'system', 'ui', null, 'info' ) );
        $logger->add( new ActivityLogEntry( 'Smart search and credentials are available as core features.', [ 'namespaces' => [ 'Search', 'Credentials' ] ], 'system', 'core', null, 'warning' ) );

        ( new ActivityLogRenderer( $config ) )->render( $logger->all() );
    }

    private function render_search_section(): void {
        echo '<div class="hpc-grid two">';
        echo CoreUi::card(
            [
                'title'     => 'X-Search concept',
                'body_html' => '<p>The Laravel reference is <span class="hpc-code">&lt;x-hexa-smart-search&gt;</span>: an AJAX typeahead for any endpoint. The WordPress core version searches posts, pages, custom post types, or filtered sources through <span class="hpc-code">wp_ajax_hexa_plugin_core_smart_search</span>.</p>',
            ]
        );
        echo CoreUi::card(
            [
                'title'     => 'Endpoint contract',
                'body_html' => '<p>Input: <span class="hpc-code">q</span>, <span class="hpc-code">source</span>, <span class="hpc-code">post_type</span>, <span class="hpc-code">limit</span>.</p><p>Output: JSON results with <span class="hpc-code">id</span>, <span class="hpc-code">name</span>, <span class="hpc-code">subtitle</span>, and <span class="hpc-code">value</span>.</p>',
            ]
        );
        echo '</div><div style="height:14px"></div>';

        ( new SmartSearchRenderer() )->render(
            [
                'id'          => 'hexa-core-smart-search-demo',
                'label'       => 'Live WordPress content search',
                'placeholder' => 'Start typing a post, page, or custom post type title...',
                'source'      => 'posts',
                'post_type'   => 'any',
                'limit'       => 8,
            ]
        );
    }

    private function render_api_key_section(): string {
        $example = ( new CredentialFieldRenderer() )->render_example(
            [
                'slug'     => 'openai',
                'key_name' => 'api_key',
                'label'    => 'OpenAI API Key',
                'provider' => 'OpenAI',
                'steps'    => [
                    'Open the provider dashboard.',
                    'Create a restricted API key for the plugin integration.',
                    'Paste it into the core credential field.',
                    'Use the plugin-specific Test key action before enabling automation.',
                ],
            ]
        );

        $code = <<<'CODE'
$store = new \Hexa\PluginCore\CredentialVault\CredentialStore();
$store->store('openai', 'api_key', $raw_key);
$key = $store->get('openai', 'api_key');
$masked = $store->get_masked('openai', 'api_key');
$exists = $store->exists('openai', 'api_key');
CODE;

        return '<div class="hpc-grid two">'
            . CoreUi::card(
                [
                    'title'     => 'Credential structure',
                    'body_html' => '<p>WordPress equivalent of Laravel <span class="hpc-code">CredentialService</span>. Stores secrets under <span class="hpc-code">hpc_cred_{slug}_{keyName}</span>, encrypts values with the WordPress auth salt, masks display values, and exposes exists/get/delete helpers.</p>',
                ]
            )
            . CoreUi::card(
                [
                    'title'     => 'Usage example',
                    'body_html' => '<pre class="hpc-readme">' . esc_html( $code ) . '</pre>',
                ]
            )
            . '</div><div style="height:14px"></div>'
            . CoreUi::card(
                [
                    'title'     => 'Visual example',
                    'body_html' => $example,
                ]
            );
    }

    private function render_error_logs_section(): string {
        return '<div class="hpc-grid two">'
            . CoreUi::card(
                [
                    'title'     => 'Core log viewer',
                    'body_html' => '<p><span class="hpc-code">ErrorLogPanelRenderer</span> owns log source summaries, tabs, search, highlighted rows, and dark log output.</p>',
                ]
            )
            . CoreUi::card(
                [
                    'title'     => 'HWS migration',
                    'body_html' => '<p>HWS Overview now uses the core error-log viewer. The cleaner cron/settings system remains in HWS until the scheduler/options abstraction is moved.</p>',
                ]
            )
            . CoreUi::card(
                [
                    'title'     => 'Reusable classes',
                    'body_html' => '<ul class="hpc-list"><li><span class="hpc-code">ErrorLogSource</span></li><li><span class="hpc-code">ErrorLogReader</span></li><li><span class="hpc-code">ErrorLogClassifier</span></li><li><span class="hpc-code">ErrorLogPanelRenderer</span></li></ul>',
                ]
            )
            . CoreUi::card(
                [
                    'title'     => 'Next extraction',
                    'body_html' => '<p>Move log cleaning schedules, retention policy, manual cleanup actions, and activity output into core system tools.</p>',
                ]
            )
            . '</div>';
    }

    private function render_field_structures_section(): string {
        return ( new FieldStructureRenderer() )->render(
            [
                [
                    "id"           => "example_article_faqs",
                    "label"        => "Article FAQ ACF",
                    "type"         => "acf",
                    "setting_key"  => "post_faqs_acf_enabled",
                    "enabled"      => true,
                    "registered"   => true,
                    "acf_group_key" => "group_example_article_faqs",
                    "location"     => "post, press-release, imported-news",
                    "description"  => "Structured FAQ rows that feed shortcode output and FAQPage JSON-LD.",
                    "instructions" => "Host plugins pass the setting key and save action. Core renders the row, toggle, docs, and status shell.",
                    "fields"       => [ "question", "answer", "enabled_for_schema" ],
                    "dependencies" => [ "ACF Pro", "host schema module" ],
                    "code_example" => "[example_post_faqs]",
                    "test_report"  => "The editor should show FAQ rows and the schema scanner should detect FAQPage when rows are enabled.",
                ],
                [
                    "id"           => "example_article_type_taxonomy",
                    "label"        => "Article Type Taxonomy",
                    "type"         => "taxonomy",
                    "setting_key"  => "article_types_enabled",
                    "enabled"      => true,
                    "registered"   => true,
                    "object_name"  => "example_article_type",
                    "location"     => "post editor taxonomy metabox",
                    "description"  => "Controlled taxonomy choices that map editor selections to schema article types.",
                    "fields"       => [ "editorial-news", "analysis", "opinion", "press-release" ],
                    "code_example" => "register_taxonomy(\"example_article_type\", [\"post\"]);",
                    "test_report"  => "The taxonomy exists and terms are available in the editor.",
                ],
            ],
            [
                "title"       => "Field Structures Catalog",
                "description" => "Examples for ACF groups, CPTs, taxonomies, and option-backed toggles. Host plugins supply the real definitions.",
            ]
        );
    }

}
