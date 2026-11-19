# Inline shortcode styles
Namespace: Hexa\PluginCore\PublicComponents. Source: src/PublicComponents/InlineStyles.php.
Call InlineStyles::enqueue('your-plugin-style', $css) from the renderer.
WordPress owns registered/done handles; the CSS is added once. Repeated renders and discarded indexer output do not consume a renderer flag.
A footer callback prints still-pending handles when a widget renders after wp_head; admin_footer supports admin previews.
An empty CSS string registers nothing. Hosts own CSS and the feature switch. Use a unique handle per distinct CSS payload.
Test: Base Tools tests/newsletter-web-context.php loads actual WordPress APIs without WP_CLI or the_content,
discards an indexer expansion, renders two visible forms, and verifies one complete stylesheet after repeated print calls.
