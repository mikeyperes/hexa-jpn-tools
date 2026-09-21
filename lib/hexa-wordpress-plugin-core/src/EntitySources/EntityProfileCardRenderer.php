<?php

namespace Hexa\PluginCore\EntitySources;

final class EntityProfileCardRenderer {
    /** @param array<string,mixed> $entity */
    public function render( array $entity ): string {
        if ( 'user' !== (string) ( $entity['kind'] ?? '' ) || ! ( $entity['object'] ?? null ) instanceof \WP_User ) {
            return $this->post_summary( $entity );
        }

        $user       = $entity['object'];
        $context    = 'user_' . (int) $entity['id'];
        $additional = $this->acf_array( 'additional', $context );
        $urls       = $this->acf_array( 'urls', $context );
        $title      = $this->first_value( [ $additional['title'] ?? '', $this->acf_value( 'team_member_title', $context ), $this->acf_value( 'subtitle', $context ) ] );
        $location   = $this->first_value( [ $this->acf_value( 'location', $context ), $additional['location'] ?? '' ] );
        $public_email = $this->first_value( [ $additional['public_email'] ?? '', $this->acf_value( 'public_email', $context ) ] );
        $public_phone = $this->first_value( [ $additional['public_phone'] ?? '', $this->acf_value( 'public_phone', $context ) ] );
        $photos     = $this->photos( $entity, $context );
        $socials    = $this->socials( $urls, $context, (string) $user->user_url );

        $identity = [
            'Full name'      => trim( (string) $user->first_name . ' ' . (string) $user->last_name ),
            'Display name'   => (string) $user->display_name,
            'Username'       => (string) $user->user_login,
            'Account email'  => (string) $user->user_email,
            'Public email'   => $public_email,
            'Public phone'   => $public_phone,
            'Title'          => $title,
            'Location'       => $location,
            'WordPress role' => implode( ', ', array_map( static fn( string $role ): string => ucwords( str_replace( '_', ' ', $role ) ), (array) $user->roles ) ),
            'Website'        => (string) $user->user_url,
            'Author archive' => (string) ( $entity['view_url'] ?? '' ),
        ];

        ob_start();
        ?>
        <div class="hpc-entity-profile">
            <div class="hpc-entity-profile-head">
                <?php if ( $photos ) : ?><img src="<?php echo esc_url( $photos[0]['url'] ); ?>" alt="<?php echo esc_attr( (string) $user->display_name ); ?>"><?php endif; ?>
                <div><h3><?php echo esc_html( (string) $user->display_name ); ?></h3><?php if ( '' !== $title ) : ?><p><?php echo esc_html( $title ); ?></p><?php endif; ?><span><?php echo esc_html( '@' . (string) $user->user_login ); ?></span></div>
            </div>

            <section class="hpc-entity-profile-section">
                <h4>Author identity</h4>
                <dl class="hpc-entity-profile-details">
                    <?php foreach ( $identity as $label => $value ) : ?>
                        <div><dt><?php echo esc_html( $label ); ?></dt><dd class="<?php echo '' === trim( (string) $value ) ? 'is-empty' : ''; ?>"><?php echo $this->linked_value( (string) $value ); ?></dd></div>
                    <?php endforeach; ?>
                </dl>
            </section>

            <section class="hpc-entity-profile-section">
                <h4>Social and public links</h4>
                <?php if ( $socials ) : ?><dl class="hpc-entity-socials"><?php foreach ( $socials as $label => $url ) : ?><div><dt><?php echo esc_html( $label ); ?></dt><dd><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $url ); ?></a></dd></div><?php endforeach; ?></dl><?php else : ?><p class="hpc-small">No social URLs are set on this author.</p><?php endif; ?>
            </section>

            <section class="hpc-entity-profile-section">
                <h4>Profile photos</h4>
                <?php if ( $photos ) : ?><div class="hpc-entity-photos"><?php foreach ( $photos as $photo ) : ?><figure><img src="<?php echo esc_url( $photo['url'] ); ?>" alt="<?php echo esc_attr( $photo['label'] ); ?>"><figcaption><?php echo esc_html( $photo['label'] ); ?></figcaption></figure><?php endforeach; ?></div><?php else : ?><p class="hpc-small">No profile photos are available.</p><?php endif; ?>
            </section>

            <?php if ( '' !== trim( (string) $user->description ) ) : ?><section class="hpc-entity-profile-section"><h4>Biography</h4><p class="hpc-entity-biography"><?php echo esc_html( (string) $user->description ); ?></p></section><?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /** @param array<string,mixed> $entity */
    private function post_summary( array $entity ): string {
        $rows = [
            'Name' => (string) ( $entity['name'] ?? '' ),
            'Content type' => (string) ( $entity['post_type'] ?? '' ),
            'Status' => (string) ( $entity['status'] ?? '' ),
            'Attached WordPress author' => (string) ( $entity['attached_user_name'] ?? '' ),
        ];
        $html = '<div class="hpc-entity-profile"><section class="hpc-entity-profile-section"><h4>Source identity</h4><dl class="hpc-entity-profile-details">';
        foreach ( $rows as $label => $value ) {
            $html .= '<div><dt>' . esc_html( $label ) . '</dt><dd class="' . ( '' === $value ? 'is-empty' : '' ) . '">' . esc_html( '' !== $value ? $value : 'Not set' ) . '</dd></div>';
        }
        return $html . '</dl></section></div>';
    }

    /** @return array<int,array{url:string,label:string}> */
    private function photos( array $entity, string $context ): array {
        $candidates = [
            [ 'value' => $this->acf_value( 'profile_photo', $context ), 'label' => 'Profile photo' ],
            [ 'value' => (string) ( $entity['image_url'] ?? '' ), 'label' => 'WordPress avatar' ],
        ];
        $gallery = $this->acf_value( 'photos', $context );
        if ( is_array( $gallery ) ) {
            foreach ( $gallery as $index => $photo ) {
                $candidates[] = [ 'value' => $photo, 'label' => 'Photo ' . ( $index + 1 ) ];
            }
        }

        $photos = [];
        foreach ( $candidates as $candidate ) {
            $url = $this->image_url( $candidate['value'] );
            if ( '' === $url || isset( $photos[ $url ] ) ) {
                continue;
            }
            $photos[ $url ] = [ 'url' => $url, 'label' => (string) $candidate['label'] ];
        }
        return array_values( $photos );
    }

    /** @return array<string,string> */
    private function socials( array $urls, string $context, string $website ): array {
        $known = [
            'website' => 'Website', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn',
            'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'x' => 'X', 'twitter' => 'X', 'threads' => 'Threads',
            'muckrack' => 'Muck Rack', 'wikipedia' => 'Wikipedia', 'github' => 'GitHub', 'crunchbase' => 'Crunchbase',
            'imdb' => 'IMDb', 'f6s' => 'F6S', 'soundcloud' => 'SoundCloud', 'the_org' => 'The Org',
            'wellfound' => 'Wellfound', 'whatsapp' => 'WhatsApp', 'telegram' => 'Telegram', 'signal' => 'Signal',
            'calendly' => 'Calendly', 'amazon' => 'Amazon', 'audible' => 'Audible',
        ];
        if ( '' !== $website && empty( $urls['website'] ) ) {
            $urls['website'] = $website;
        }
        foreach ( $known as $key => $label ) {
            if ( ! isset( $urls[ $key ] ) ) {
                $direct = $this->acf_value( $key, $context );
                if ( is_string( $direct ) && '' !== $direct ) {
                    $urls[ $key ] = $direct;
                }
            }
        }
        $socials = [];
        foreach ( $known as $key => $label ) {
            $url = is_scalar( $urls[ $key ] ?? null ) ? trim( (string) $urls[ $key ] ) : '';
            if ( '' !== $url && preg_match( '#^https?://#i', $url ) ) {
                $socials[ $label ] = $url;
            }
        }
        return $socials;
    }

    private function acf_value( string $name, string $context ): mixed {
        return function_exists( 'get_field' ) ? get_field( $name, $context ) : null;
    }

    private function acf_array( string $name, string $context ): array {
        $value = $this->acf_value( $name, $context );
        return is_array( $value ) ? $value : [];
    }

    private function image_url( mixed $value ): string {
        if ( is_array( $value ) ) {
            $value = $value['sizes']['medium'] ?? $value['url'] ?? $value['ID'] ?? $value['id'] ?? '';
        } elseif ( is_object( $value ) && isset( $value->ID ) ) {
            $value = (int) $value->ID;
        }
        if ( is_numeric( $value ) && function_exists( 'wp_get_attachment_image_url' ) ) {
            return (string) ( wp_get_attachment_image_url( (int) $value, 'medium' ) ?: '' );
        }
        return is_string( $value ) && preg_match( '#^https?://#i', $value ) ? $value : '';
    }

    private function first_value( array $values ): string {
        foreach ( $values as $value ) {
            if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
                return trim( (string) $value );
            }
        }
        return '';
    }

    private function linked_value( string $value ): string {
        if ( '' === trim( $value ) ) {
            return '<span class="is-empty">Not set</span>';
        }
        if ( preg_match( '#^https?://#i', $value ) ) {
            return '<a href="' . esc_url( $value ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $value ) . '</a>';
        }
        if ( is_email( $value ) ) {
            return '<a href="mailto:' . esc_attr( $value ) . '">' . esc_html( $value ) . '</a>';
        }
        return esc_html( $value );
    }
}
