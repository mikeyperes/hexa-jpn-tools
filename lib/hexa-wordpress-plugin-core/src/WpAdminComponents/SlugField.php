<?php
namespace Hexa\PluginCore\WpAdminComponents;

use Hexa\PluginCore\DataNormalization\SlugConverter;

/**
 * A name input with a slug input that fills itself as the name is typed.
 *
 * Typing in the slug box takes it over (it stops following the name); emptying it hands it back, so the next name keystroke fills it again.
 * Whatever is typed in the slug box is cleaned to slug form live. Works on markup added later by
 * AJAX, because the script listens on the document. Server code must still save
 * SlugConverter::from_name() of the posted slug.
 *
 * Args: id, name_field, slug_field, name_label, slug_label, name_value, slug_value, name_placeholder,
 * separator ('-' or '_'), max, slug_readonly (bool, for an existing record whose slug cannot change),
 * preview (text with {slug}, for example '[my_shortcode id="{slug}"]'), help.
 */
final class SlugField {
    public static function render( array $args ): string {
        $id        = sanitize_key( (string) ( $args['id'] ?? 'hpc-slug' ) );
        $separator = '_' === ( $args['separator'] ?? '-' ) ? '_' : '-';
        $max       = (int) ( $args['max'] ?? SlugConverter::DEFAULT_MAX );
        $slug      = (string) ( $args['slug_value'] ?? '' );
        $readonly  = ! empty( $args['slug_readonly'] );
        $preview   = (string) ( $args['preview'] ?? '' );
        $locked    = '' !== $slug && $slug !== SlugConverter::from_name( (string) ( $args['name_value'] ?? '' ), $separator, $max );

        return self::assets()
            . '<div class="hpc-slug-field" data-separator="' . esc_attr( $separator ) . '" data-max="' . $max . '"' . ( $locked || $readonly ? ' data-locked="1"' : '' ) . '>'
            . '<p class="hpc-slug-row"><label for="' . esc_attr( $id ) . '-name"><strong>' . esc_html( (string) ( $args['name_label'] ?? 'Name' ) ) . '</strong></label><br>'
            . '<input type="text" class="regular-text hpc-slug-name" id="' . esc_attr( $id ) . '-name" name="' . esc_attr( (string) ( $args['name_field'] ?? 'name' ) ) . '" value="' . esc_attr( (string) ( $args['name_value'] ?? '' ) ) . '" placeholder="' . esc_attr( (string) ( $args['name_placeholder'] ?? '' ) ) . '" maxlength="200" autocomplete="off"></p>'
            . '<p class="hpc-slug-row"><label for="' . esc_attr( $id ) . '-slug"><strong>' . esc_html( (string) ( $args['slug_label'] ?? 'Slug' ) ) . '</strong></label><br>'
            . '<input type="text" class="regular-text code hpc-slug-value" id="' . esc_attr( $id ) . '-slug" name="' . esc_attr( (string) ( $args['slug_field'] ?? 'slug' ) ) . '" value="' . esc_attr( $slug ) . '" maxlength="' . $max . '" autocomplete="off" spellcheck="false"' . ( $readonly ? ' readonly' : '' ) . '>'
            . ( '' !== (string) ( $args['help'] ?? '' ) ? '<span class="hpc-slug-help">' . esc_html( (string) $args['help'] ) . '</span>' : '' ) . '</p>'
            . ( '' !== $preview ? '<p class="hpc-slug-preview-row"><code class="hpc-slug-preview" data-template="' . esc_attr( $preview ) . '">' . esc_html( str_replace( '{slug}', $slug, $preview ) ) . '</code></p>' : '' )
            . '</div>';
    }

    private static function assets(): string {
        static $done = false;
        if ( $done ) {
            return '';
        }
        $done = true;

        return '<style>.hpc-slug-field .hpc-slug-row{margin:0 0 12px}.hpc-slug-field .hpc-slug-help{display:block;color:#50575e;font-size:13px;margin-top:4px}.hpc-slug-field .hpc-slug-preview{display:inline-block;padding:4px 8px}</style>'
            . '<script>' . SlugConverter::js() . '(function(){if(window.hexaSlugFieldReady)return;window.hexaSlugFieldReady=true;'
            . 'function box(el){return el.closest?el.closest(".hpc-slug-field"):null;}'
            . 'function sync(f,v){var s=f.querySelector(".hpc-slug-value"),p=f.querySelector(".hpc-slug-preview");if(s&&v!==null)s.value=v;if(p)p.textContent=p.getAttribute("data-template").split("{slug}").join(s?s.value:"");}'
            . 'document.addEventListener("input",function(e){var t=e.target,f=box(t);if(!f)return;var sep=f.getAttribute("data-separator"),max=parseInt(f.getAttribute("data-max"),10);'
            . 'if(t.classList.contains("hpc-slug-name")){if(f.getAttribute("data-locked")!=="1")sync(f,HexaSlug.fromName(t.value,sep,max));}'
            . 'else if(t.classList.contains("hpc-slug-value")&&!t.readOnly){var end=/[\s\-_]$/.test(t.value)&&t.value.length<max;var v=HexaSlug.fromName(t.value,sep,max)+(end?sep:"");'
            . 'if(v===""||v===sep){f.removeAttribute("data-locked");sync(f,"");}else{f.setAttribute("data-locked","1");sync(f,v);}}});'
            . 'document.addEventListener("change",function(e){var t=e.target,f=box(t);if(f&&t.classList.contains("hpc-slug-value")&&!t.readOnly)sync(f,HexaSlug.fromName(t.value,f.getAttribute("data-separator"),parseInt(f.getAttribute("data-max"),10)));});'
            . '})();</script>';
    }
}
