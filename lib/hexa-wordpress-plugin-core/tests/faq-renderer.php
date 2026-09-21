<?php

declare(strict_types=1);

function sanitize_text_field( string $value ): string { return trim( strip_tags( $value ) ); }
function sanitize_key( string $value ): string { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', $value ) ?: '' ); }
function wp_kses_post( string $value ): string { return strip_tags( $value, '<p><strong>' ); }
function wp_strip_all_tags( string $value ): string { return strip_tags( $value ); }
function esc_attr( string $value ): string { return htmlspecialchars( $value, ENT_QUOTES ); }
function esc_html( string $value ): string { return htmlspecialchars( $value, ENT_QUOTES ); }

require dirname( __DIR__ ) . '/src/FaqSets/FaqSetManager.php';

$manager = new Hexa\PluginCore\FaqSets\FaqSetManager();
$html = $manager->renderItems(
    [ [ 'question' => 'What is this?', 'answer' => '<p>A shared renderer.</p>' ] ],
    [
        'wrapper_tag' => 'ul', 'wrapper_class' => 'host-list',
        'item_tag' => 'li', 'item_class' => 'host-item',
        'question_tag' => 'h3', 'question_class' => 'host-question',
        'answer_class' => 'host-answer',
    ]
);

foreach ( [ '<ul class="host-list">', '<li class="host-item">', '<h3 class="host-question">What is this?</h3>', '<div class="host-answer"><p>A shared renderer.</p></div>' ] as $needle ) {
    if ( ! str_contains( $html, $needle ) ) {
        fwrite( STDERR, "FAIL: Generic FAQ host markup is missing {$needle}.\n" );
        exit( 1 );
    }
}

echo "PASS: FAQ rendering supports reusable host markup without duplicating normalization.\n";
