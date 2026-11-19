<?php
namespace Hexa\PluginCore\DataNormalization;

/**
 * Turns a human name into a URL- and key-safe slug ("Weekly Digest!" -> "weekly-digest").
 *
 * The same rules run in the browser through SlugConverter::js() (used by WpAdminComponents\SlugField),
 * so what an admin sees while typing is what the server saves.
 */
final class SlugConverter {
    public const DEFAULT_MAX = 80;

    public static function from_name( string $name, string $separator = '-', int $max = self::DEFAULT_MAX ): string {
        $separator = '_' === $separator ? '_' : '-';
        $slug      = strtr( function_exists( 'mb_strtolower' ) ? mb_strtolower( $name, 'UTF-8' ) : strtolower( $name ), [ 'ß' => 'ss', 'æ' => 'ae', 'œ' => 'oe', 'ø' => 'o', 'đ' => 'd', 'ł' => 'l', '&' => ' and ' ] );
        $slug      = strtolower( function_exists( 'remove_accents' ) ? remove_accents( $slug ) : $slug );
        $slug      = trim( (string) preg_replace( '/[^a-z0-9]+/', $separator, $slug ), '-_' );
        if ( $max > 0 && strlen( $slug ) > $max ) {
            $slug = rtrim( substr( $slug, 0, $max ), '-_' );
        }

        return $slug;
    }

    /** True when the value is already a slug these rules would produce (lowercase letters, numbers, single separators). */
    public static function is_valid( string $slug, string $separator = '-', int $max = self::DEFAULT_MAX ): bool {
        return '' !== $slug && $slug === self::from_name( $slug, $separator, $max );
    }

    /** Browser twin of from_name(): defines window.HexaSlug.fromName(name, separator, max). */
    public static function js(): string {
        return 'window.HexaSlug=window.HexaSlug||{fromName:function(n,sep,max){sep=sep==="_"?"_":"-";max=max===undefined?' . self::DEFAULT_MAX . ':max;'
            . 'var m={"ß":"ss","æ":"ae","œ":"oe","ø":"o","đ":"d","ł":"l","&":" and "};'
            . 'var s=String(n||"").normalize("NFD").replace(/[̀-ͯ]/g,"").toLowerCase().replace(/[ßæœøđł&]/g,function(c){return m[c];});'
            . 's=s.replace(/[^a-z0-9]+/g,sep).replace(/^[-_]+|[-_]+$/g,"");if(max>0&&s.length>max)s=s.slice(0,max).replace(/[-_]+$/,"");return s;}};';
    }
}
