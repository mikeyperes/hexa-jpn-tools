<?php

declare(strict_types=1);

$core = dirname( __DIR__ );

if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        private string $code;
        private string $message;
        private mixed $data = null;
        public function __construct( string $code = '', string $message = '' ) { $this->code = $code; $this->message = $message; }
        public function get_error_code(): string { return $this->code; }
        public function get_error_message(): string { return $this->message; }
        public function add_data( mixed $data ): void { $this->data = $data; }
    }
}
if ( ! function_exists( 'is_wp_error' ) ) { function is_wp_error( mixed $value ): bool { return $value instanceof WP_Error; } }
if ( ! function_exists( 'wp_mkdir_p' ) ) { function wp_mkdir_p( string $path ): bool { return is_dir( $path ) || mkdir( $path, 0777, true ); } }
if ( ! function_exists( 'untrailingslashit' ) ) { function untrailingslashit( string $path ): string { return rtrim( $path, '/\\' ); } }

require $core . '/bootstrap.php';
require $core . '/src/PluginUpdates/UpdaterFilesystem.php';
require $core . '/src/CorePackageUpdates/CorePackageFleetSynchronizer.php';

$workspace = sys_get_temp_dir() . '/hexa-core-sync-' . bin2hex( random_bytes( 5 ) );
$new = $workspace . '/new';
$old = $workspace . '/old';
foreach ( [ $new, $old ] as $root ) {
    mkdir( $root . '/src', 0777, true );
    file_put_contents( $root . '/bootstrap.php', "<?php\n" );
}
file_put_contents( $new . '/VERSION', "3.0.0\n" );
file_put_contents( $new . '/src/Current.php', "<?php\n// current\n" );
file_put_contents( $old . '/VERSION', "2.1.4\n" );
file_put_contents( $old . '/src/Current.php', "<?php\n// old\n" );
file_put_contents( $old . '/src/Removed.php', "<?php\n// stale\n" );
file_put_contents( $new . '/PACKAGE_HASH', HexaPluginCorePackageRegistry::source_hash( $new ) . "\n" );
file_put_contents( $old . '/PACKAGE_HASH', HexaPluginCorePackageRegistry::source_hash( $old ) . "\n" );

$report = [ 'candidates' => [
    [ 'host' => 'new-host', 'root' => $new, 'minimum_version' => '2.0.0', 'maximum_version' => '' ],
    [ 'host' => 'old-host', 'root' => $old, 'minimum_version' => '2.0.0', 'maximum_version' => '' ],
] ];
$result = ( new Hexa\PluginCore\CorePackageUpdates\CorePackageFleetSynchronizer() )->synchronize( $report );
$passed = ! is_wp_error( $result )
    && '3.0.0' === trim( (string) file_get_contents( $old . '/VERSION' ) )
    && ! file_exists( $old . '/src/Removed.php' )
    && HexaPluginCorePackageRegistry::source_hash( $old ) === HexaPluginCorePackageRegistry::source_hash( $new )
    && [ 'old-host' ] === ( $result['updated_hosts'] ?? [] );

$transaction_source = $workspace . '/transaction-source';
$transaction_one    = $workspace . '/transaction-one';
$transaction_two    = $workspace . '/transaction-two';
foreach ( [ $transaction_source, $transaction_one, $transaction_two ] as $path ) {
    mkdir( $path, 0777, true );
}
file_put_contents( $transaction_source . '/value.txt', 'new' );
file_put_contents( $transaction_one . '/value.txt', 'old-one' );
file_put_contents( $transaction_two . '/value.txt', 'old-two' );
$plan_one = Hexa\PluginCore\PluginUpdates\UpdaterFilesystem::stage_directory_replacement( $transaction_source, $transaction_one );
$plan_two = Hexa\PluginCore\PluginUpdates\UpdaterFilesystem::stage_directory_replacement( $transaction_source, $transaction_two );
$transaction_passed = ! is_wp_error( $plan_one ) && ! is_wp_error( $plan_two );
if ( $transaction_passed ) {
    $plan_one = Hexa\PluginCore\PluginUpdates\UpdaterFilesystem::commit_directory_replacement( $plan_one );
    Hexa\PluginCore\PluginUpdates\UpdaterFilesystem::delete_directory( (string) $plan_two['stage'] );
    $failed_commit = Hexa\PluginCore\PluginUpdates\UpdaterFilesystem::commit_directory_replacement( $plan_two );
    $rollback_one  = is_wp_error( $plan_one ) ? $plan_one : Hexa\PluginCore\PluginUpdates\UpdaterFilesystem::rollback_directory_replacement( $plan_one );
    $rollback_two  = Hexa\PluginCore\PluginUpdates\UpdaterFilesystem::rollback_directory_replacement( $plan_two );
    $transaction_passed = ! is_wp_error( $plan_one )
        && is_wp_error( $failed_commit )
        && ! is_wp_error( $rollback_one )
        && ! is_wp_error( $rollback_two )
        && 'old-one' === file_get_contents( $transaction_one . '/value.txt' )
        && 'old-two' === file_get_contents( $transaction_two . '/value.txt' );
}

Hexa\PluginCore\PluginUpdates\UpdaterFilesystem::delete_directory( $workspace );
if ( ! $passed || ! $transaction_passed ) {
    fwrite( STDERR, "FAIL: automatic Core fleet synchronization did not atomically replace the stale package.\n" );
    exit( 1 );
}
echo "PASS: plugin updates propagate the newest verified Core package, remove stale files, and roll the fleet back after a partial commit failure.\n";
