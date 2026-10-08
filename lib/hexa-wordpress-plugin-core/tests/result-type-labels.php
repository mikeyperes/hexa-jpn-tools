<?php

declare(strict_types=1);

$actions = [];

function add_action( string $hook, callable $callback ): bool {
    global $actions;
    $actions[] = $hook;
    return true;
}

function get_post_type_object( string $post_type ): ?object {
    return 'event' === $post_type ? (object) [ 'labels' => (object) [ 'singular_name' => 'Event' ] ] : null;
}

$root = dirname( __DIR__ );
require $root . '/src/SearchQuery/ResultTypeLabels.php';

use Hexa\PluginCore\SearchQuery\ResultTypeLabels;

function assert_same( mixed $expected, mixed $actual, string $message ): void {
    if ( $expected !== $actual ) {
        fwrite( STDERR, $message . ': expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . "\n" );
        exit( 1 );
    }
}

assert_same( 'Event', ResultTypeLabels::label_for( 'event' ), 'Unmapped types fall back to their singular name' );
assert_same( '', ResultTypeLabels::label_for( 'unknown' ), 'Unknown types without a default have no label' );

ResultTypeLabels::register( [ 'post' => ' <b>Press</b>   Release ', 'Press-Release' => 'External PR', 'bad key!' => '' ], 'Site Content' );
assert_same( 'Press Release', ResultTypeLabels::label_for( 'post' ), 'Labels are stripped and collapsed' );
assert_same( 'External PR', ResultTypeLabels::label_for( 'press-release' ), 'Post type keys are normalized' );
assert_same( 'Site Content', ResultTypeLabels::label_for( 'event' ), 'The default wins over the singular name' );

ResultTypeLabels::register( [ 'post' => 'Release' ] );
assert_same( 'Release', ResultTypeLabels::label_for( 'post' ), 'A later host replaces the same type' );
assert_same( 'Site Content', ResultTypeLabels::label_for( 'page' ), 'An empty default keeps the earlier default' );
assert_same( [ 'elementor/dynamic_tags/register' ], $actions, 'The Elementor tag hook is added once' );

ResultTypeLabels::reset();
assert_same( 'Event', ResultTypeLabels::label_for( 'event' ), 'Reset forgets labels and the default' );

echo "Result type labels passed.\n";
