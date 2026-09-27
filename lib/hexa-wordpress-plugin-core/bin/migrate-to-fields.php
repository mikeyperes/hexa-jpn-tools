<?php
/**
 * Migrate a host plugin from direct ACF calls to Hexa\PluginCore\Fields.
 *
 * Usage: php bin/migrate-to-fields.php <plugin-root> [--dry-run]
 *
 * Token-based (comments and strings are untouched except the `acf/` hook name
 * passed to add_action/add_filter). Skips bundled Core copies and vendor
 * folders. Prints every change and every spot that needs manual review:
 * calls that run at file load (before Core's autoloader exists), ACF plugin
 * detection, and ACF Pro dependency declarations.
 */

if ( PHP_SAPI !== 'cli' ) {
    exit;
}

$root = realpath( $argv[1] ?? '' );
$dry  = in_array( '--dry-run', $argv, true );
if ( false === $root || ! is_dir( $root ) ) {
    fwrite( STDERR, "Usage: php migrate-to-fields.php <plugin-root> [--dry-run]\n" );
    exit( 1 );
}

$field = '\\Hexa\\PluginCore\\Fields\\Field::';
$groups = '\\Hexa\\PluginCore\\Fields\\FieldGroups::';
$map = [
    'get_field' => $field . 'get', 'the_field' => $field . 'the', 'update_field' => $field . 'update',
    'delete_field' => $field . 'delete', 'get_fields' => $field . 'all', 'get_field_object' => $field . 'object',
    'get_field_objects' => $field . 'objects', 'have_rows' => $field . 'have_rows', 'the_row' => $field . 'the_row',
    'get_sub_field' => $field . 'get_sub_field', 'the_sub_field' => $field . 'the_sub_field',
    'get_row_index' => $field . 'get_row_index', 'get_row_layout' => $field . 'get_row_layout', 'reset_rows' => $field . 'reset_rows',
    'acf_add_local_field_group' => $groups . 'add', 'acf_remove_local_field_group' => $groups . 'remove',
    'acf_get_field_group' => $groups . 'get_group', 'acf_get_local_field_group' => $groups . 'get_group',
    'acf_get_fields' => $groups . 'fields', 'acf_get_field' => $groups . 'get_field', 'acf_get_local_field' => $groups . 'get_field',
    'acf_get_field_groups' => $groups . 'all', 'acf_get_local_field_groups' => $groups . 'all', 'acf_get_local_fields' => $groups . 'fields',
    'acf_add_options_page' => '\\Hexa\\PluginCore\\Fields\\OptionsPages::add', 'acf_add_options_sub_page' => '\\Hexa\\PluginCore\\Fields\\OptionsPages::add_sub',
    'acf_form_head' => '\\Hexa\\PluginCore\\Fields\\Form::head', 'acf_form' => '\\Hexa\\PluginCore\\Fields\\Form::render',
    'acf_enqueue_scripts' => '\\Hexa\\PluginCore\\Fields\\Form::enqueue', 'acf_get_form_data' => '\\Hexa\\PluginCore\\Fields\\Form::data', 'acf_render_field_wrap' => '\\Hexa\\PluginCore\\Fields\\Form::field',
];
$guards = array_merge( array_keys( $map ), [ 'acf' ] );
$review_patterns = [
    '/advanced-custom-fields(-pro)?/i' => 'ACF plugin reference',
    '/class_exists\(\s*[\'"]\\\\?ACF[\'"]/' => 'ACF class detection',
    '/ACF Pro is required|requires? ACF|ACF Pro required/i' => 'ACF requirement text',
    '/\bacf_[a-z_]+\(/' => 'remaining ACF API call',
];

$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
$totals = [ 'files' => 0, 'changes' => 0, 'review' => 0 ];
foreach ( $files as $file ) {
    $path = $file->getPathname();
    $relative = substr( $path, strlen( $root ) + 1 );
    if ( 'php' !== strtolower( $file->getExtension() ) || preg_match( '#(^|/)(\.git|vendor|lib/hexa-wordpress-plugin-core|node_modules)(/|$)#', $relative ) ) {
        continue;
    }
    $source = (string) file_get_contents( $path );
    [ $output, $changes, $early ] = migrate( $source, $map, $guards );
    $review = [];
    foreach ( explode( "\n", $output ) as $number => $line ) {
        foreach ( $review_patterns as $pattern => $label ) {
            if ( preg_match( $pattern, $line ) && ! str_contains( $line, 'Hexa\\PluginCore\\Fields' ) ) {
                $review[] = sprintf( '  review L%d %s: %s', $number + 1, $label, trim( substr( $line, 0, 150 ) ) );
            }
        }
    }
    foreach ( $early as $line ) {
        $review[] = sprintf( '  review L%d runs at file load (before Core autoload): wrap in a plugins_loaded callback', $line );
    }
    if ( [] === $changes && [] === $review ) {
        continue;
    }
    $totals['files']++;
    $totals['changes'] += count( $changes );
    $totals['review'] += count( $review );
    echo $relative, PHP_EOL;
    foreach ( $changes as $change ) {
        echo '  ', $change, PHP_EOL;
    }
    foreach ( $review as $item ) {
        echo $item, PHP_EOL;
    }
    if ( ! $dry && $output !== $source ) {
        file_put_contents( $path, $output );
    }
}
printf( "%s: %d files, %d changes, %d review items%s\n", basename( $root ), $totals['files'], $totals['changes'], $totals['review'], $dry ? ' (dry run)' : '' );

/**
 * @param array<string,string> $map
 * @param array<int,string> $guards
 * @return array{0:string,1:array<int,string>,2:array<int,int>}
 */
function migrate( string $source, array $map, array $guards ): array {
    $tokens = token_get_all( $source );
    $out = '';
    $changes = [];
    $early = [];
    $scopes = [];          // stack of booleans: true when the brace opens a function body
    $pending_function = false;
    $count = count( $tokens );
    for ( $i = 0; $i < $count; $i++ ) {
        $token = $tokens[ $i ];
        $text = is_array( $token ) ? $token[1] : $token;
        $id = is_array( $token ) ? $token[0] : null;
        $line = is_array( $token ) ? $token[2] : 0;

        if ( T_FUNCTION === $id || T_FN === $id ) {
            $pending_function = true;
        }
        if ( '{' === $text || T_CURLY_OPEN === $id || T_DOLLAR_OPEN_CURLY_BRACES === $id ) {
            $scopes[] = $pending_function && '{' === $text;
            $pending_function = $pending_function && '{' !== $text;
            $out .= $text;
            continue;
        }
        if ( '}' === $text ) {
            array_pop( $scopes );
            $out .= $text;
            continue;
        }
        if ( ';' === $text ) {
            $pending_function = false;
        }
        $in_function = in_array( true, $scopes, true );

        $name = null;
        if ( T_STRING === $id ) {
            $name = $text;
        } elseif ( defined( 'T_NAME_FULLY_QUALIFIED' ) && T_NAME_FULLY_QUALIFIED === $id ) {
            $name = ltrim( $text, '\\' );
        }
        if ( null === $name ) {
            $out .= $text;
            continue;
        }
        $prev = previous( $tokens, $i );
        $next = next_index( $tokens, $i );
        $is_call = null !== $next && '(' === ( is_array( $tokens[ $next ] ) ? $tokens[ $next ][1] : $tokens[ $next ] );
        $is_member = null !== $prev && in_array( is_array( $prev ) ? $prev[0] : $prev, [ T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW, T_NULLSAFE_OBJECT_OPERATOR ], true );
        if ( ! $is_call || $is_member ) {
            $out .= $text;
            continue;
        }

        // function_exists( 'get_field' ) style guards.
        if ( 'function_exists' === $name ) {
            $arg = next_index( $tokens, $next );
            $close = null !== $arg ? next_index( $tokens, $arg ) : null;
            if ( null !== $arg && null !== $close && is_array( $tokens[ $arg ] ) && T_CONSTANT_ENCAPSED_STRING === $tokens[ $arg ][0]
                && ')' === ( is_array( $tokens[ $close ] ) ? $tokens[ $close ][1] : $tokens[ $close ] )
                && in_array( ltrim( trim( $tokens[ $arg ][1], '\'"' ), '\\' ), $guards, true ) ) {
                $out .= '\\Hexa\\PluginCore\\Fields\\Field::available()';
                $changes[] = sprintf( 'L%d function_exists( %s ) -> Field::available()', $line, $tokens[ $arg ][1] );
                $i = $close;
                continue;
            }
            $out .= $text;
            continue;
        }

        // add_action/add_filter( 'acf/...' ) -> Hooks::on( '...' ).
        if ( in_array( $name, [ 'add_action', 'add_filter', 'remove_action', 'remove_filter' ], true ) ) {
            $arg = next_index( $tokens, $next );
            if ( null !== $arg && is_array( $tokens[ $arg ] ) && T_CONSTANT_ENCAPSED_STRING === $tokens[ $arg ][0] && preg_match( '/^([\'"])acf\//', $tokens[ $arg ][1] ) ) {
                $method = str_starts_with( $name, 'remove' ) ? 'off' : 'on';
                // While plugins load, Core's classes are not autoloadable yet: bootstrap.php's hexa_fields_on() defers.
                $load_safe = 'on' === $method && ! $in_function;
                $out .= $load_safe ? '\\hexa_fields_on' : '\\Hexa\\PluginCore\\Fields\\Hooks::' . $method;
                for ( $j = $i + 1; $j < $arg; $j++ ) {
                    $out .= is_array( $tokens[ $j ] ) ? $tokens[ $j ][1] : $tokens[ $j ];
                }
                $out .= preg_replace( '/^([\'"])acf\//', '$1', $tokens[ $arg ][1] );
                $changes[] = sprintf( 'L%d %s( %s ) -> %s', $line, $name, $tokens[ $arg ][1], $load_safe ? 'hexa_fields_on' : 'Hooks::' . $method );
                if ( ! $in_function && ! $load_safe ) {
                    $early[] = $line;
                }
                $i = $arg;
                continue;
            }
            $out .= $text;
            continue;
        }

        if ( isset( $map[ $name ] ) ) {
            $out .= $map[ $name ];
            $changes[] = sprintf( 'L%d %s() -> %s()', $line, $name, $map[ $name ] );
            if ( ! $in_function ) {
                $early[] = $line;
            }
            continue;
        }
        $out .= $text;
    }
    return [ $out, $changes, array_values( array_unique( $early ) ) ];
}

function previous( array $tokens, int $i ): mixed {
    for ( $j = $i - 1; $j >= 0; $j-- ) {
        if ( ! is_array( $tokens[ $j ] ) || ! in_array( $tokens[ $j ][0], [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ], true ) ) {
            return $tokens[ $j ];
        }
    }
    return null;
}

function next_index( array $tokens, ?int $i ): ?int {
    if ( null === $i ) {
        return null;
    }
    $count = count( $tokens );
    for ( $j = $i + 1; $j < $count; $j++ ) {
        if ( ! is_array( $tokens[ $j ] ) || ! in_array( $tokens[ $j ][0], [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ], true ) ) {
            return $j;
        }
    }
    return null;
}
