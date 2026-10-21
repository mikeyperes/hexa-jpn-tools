<?php

declare(strict_types=1);

// Client-friendly field view: developer notes only outside the client view.

function sanitize_key( string $key ): string { return strtolower( (string) preg_replace( '/[^a-z0-9_\-]/i', '', $key ) ); }
function user_can( WP_User $user, string $cap ): bool { return 'manage_options' === $cap && in_array( 'administrator', $user->roles, true ); }
class WP_User {
    public function __construct( public int $ID = 0, public array $roles = [] ) {}
    public function exists(): bool { return $this->ID > 0; }
}

require dirname( __DIR__ ) . '/src/Fields/Audience.php';

use Hexa\PluginCore\Fields\Audience;

$expect = static function ( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$admin    = new WP_User( 1, [ 'administrator' ] );
$customer = new WP_User( 2, [ 'hexa_pr_wire_user' ] );
$editor   = new WP_User( 3, [ 'editor' ] );

$group = [ 'key' => 'group_x', 'fields' => [
    [ 'key' => 'f1', 'instructions' => 'Social media and web URLs.', 'developer_instructions' => 'Shortcode: <code>[company id="url_x"]</code>', 'sub_fields' => [
        [ 'key' => 'f2', 'instructions' => '', 'developer_instructions' => 'Shortcode: <code>[founder id="url_x"]</code>' ],
    ] ],
    [ 'key' => 'f3', 'instructions' => 'Plain help.' ],
    [ 'key' => 'f4', 'type' => 'flexible_content', 'layouts' => [ 'l1' => [ 'sub_fields' => [ [ 'key' => 'f5', 'developer_instructions' => 'meta key: x' ] ] ] ] ],
] ];

$client = Audience::apply( $group, $customer );
$expect( 'Social media and web URLs.' === $client['fields'][0]['instructions'], 'Clients see only the friendly instructions.' );
$expect( '' === $client['fields'][0]['sub_fields'][0]['instructions'], 'Sub-field developer notes are hidden from clients.' );
$expect( '' === $client['fields'][2]['layouts']['l1']['sub_fields'][0]['instructions'], 'Flexible-content layout notes are hidden from clients.' );
$expect( ! isset( $client['fields'][0]['developer_instructions'] ), 'The developer key never reaches ACF.' );

$dev = Audience::apply( $group, $admin );
$expect( 'Social media and web URLs.<br>Shortcode: <code>[company id="url_x"]</code>' === $dev['fields'][0]['instructions'], 'Administrators see friendly text followed by developer notes.' );
$expect( 'Shortcode: <code>[founder id="url_x"]</code>' === $dev['fields'][0]['sub_fields'][0]['instructions'], 'A field with only developer notes shows them to administrators.' );
$expect( 'Plain help.' === $dev['fields'][1]['instructions'], 'Fields without developer notes are unchanged.' );

$expect( Audience::client_view( [], null ), 'No signed-in user gets the client view.' );
$expect( ! Audience::client_view( [ 'enabled' => false ], $customer ), 'Turning the client view off shows developer notes to everyone.' );
$roles = [ 'enabled' => true, 'roles' => [ 'hexa_pr_wire_user' ] ];
$expect( Audience::client_view( $roles, $customer ) && ! Audience::client_view( $roles, $editor ), 'A role list gives the client view only to those roles.' );
$expect( Audience::BELOW_ADMIN === Audience::normalize( [ 'roles' => [] ] )['roles'], 'An empty role list falls back to everyone below administrator.' );
$expect( true === Audience::normalize( [] )['enabled'], 'The client view is on by default.' );

$staff = [ 'key' => 'g', 'client_view' => [ 'roles' => 'below_admin', 'hide' => true ], 'fields' => [] ];
$expect( Audience::hidden( $staff, $customer ) && ! Audience::hidden( $staff, $admin ), 'A staff-only group is hidden from clients and kept for administrators.' );
$expect( ! Audience::hidden( $group, $customer ), 'Groups are not hidden unless asked.' );

echo "PASS: client-friendly field view (14 assertions).\n";
