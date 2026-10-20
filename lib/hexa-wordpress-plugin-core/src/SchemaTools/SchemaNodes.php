<?php

namespace Hexa\PluginCore\SchemaTools;

use Hexa\PluginCore\DataNormalization\ValueNormalizer;

/**
 * Generic schema.org node builders.
 *
 * Every builder takes plain values (schema property names, or the common
 * snake_case field-row keys shown on each method) and returns one cleaned
 * node. Builders never read WordPress data and never decide which page shows
 * a node: host plugins collect their own data, call these builders, and add
 * the nodes to SchemaPage for the pages they cover.
 */
final class SchemaNodes {
    public const ORGANIZATION_TYPES = [
        'Organization',
        'Corporation',
        'LocalBusiness',
        'ProfessionalService',
        'NGO',
        'NonprofitOrganization',
        'EducationalOrganization',
        'GovernmentOrganization',
        'MedicalOrganization',
        'SportsOrganization',
        'NewsMediaOrganization',
        'OnlineBusiness',
    ];

    public const ARTICLE_TYPES = [
        'Article',
        'NewsArticle',
        'BlogPosting',
        'ReportageNewsArticle',
        'AnalysisNewsArticle',
        'OpinionNewsArticle',
        'ReviewNewsArticle',
        'BackgroundNewsArticle',
        'AdvertiserContentArticle',
        'SatiricalArticle',
        'ScholarlyArticle',
        'TechArticle',
        'Report',
    ];

    public const PAGE_TYPES = [
        'WebPage',
        'AboutPage',
        'ContactPage',
        'ProfilePage',
        'CollectionPage',
        'ItemPage',
        'SearchResultsPage',
        'FAQPage',
    ];

    /** Any typed node: @type, optional @id, then the given properties. */
    public static function node( string $type, array $properties, string $id = '' ): array {
        return self::finish( [ '@type' => $type, '@id' => $id ] + $properties );
    }

    public static function organization( array $properties, string $type = 'Organization' ): array {
        if ( ! self::named_or_identified( $properties ) ) {
            return [];
        }
        $type = in_array( $type, self::ORGANIZATION_TYPES, true ) ? $type : 'Organization';
        $properties['logo']   = self::image_value( $properties['logo'] ?? null );
        $properties['image']  = self::image_value( $properties['image'] ?? null );
        $properties['sameAs'] = self::same_as( $properties['sameAs'] ?? [] );
        $properties['email']  = ValueNormalizer::email( $properties['email'] ?? '' );
        return self::finish( [ '@type' => $type ] + $properties );
    }

    public static function person( array $properties ): array {
        if ( ! self::named_or_identified( $properties ) ) {
            return [];
        }
        $properties['image']  = self::image_value( $properties['image'] ?? null );
        $properties['sameAs'] = self::same_as( $properties['sameAs'] ?? [] );
        $properties['email']  = ValueNormalizer::email( $properties['email'] ?? '' );
        if ( isset( $properties['description'] ) ) {
            $properties['description'] = ValueNormalizer::text( $properties['description'] );
        }
        return self::finish( [ '@type' => 'Person' ] + $properties );
    }

    /**
     * @param array<string,mixed> $properties Use 'search_url' (with {search_term_string}) to add a SearchAction.
     */
    public static function website( array $properties ): array {
        $search = (string) ( $properties['search_url'] ?? '' );
        unset( $properties['search_url'] );
        if ( '' !== $search ) {
            $properties['potentialAction'] = [
                '@type'       => 'SearchAction',
                'target'      => [ '@type' => 'EntryPoint', 'urlTemplate' => $search ],
                'query-input' => 'required name=search_term_string',
            ];
        }
        return self::finish( [ '@type' => 'WebSite' ] + $properties );
    }

    public static function web_page( array $properties, string $type = 'WebPage' ): array {
        $type = in_array( $type, self::PAGE_TYPES, true ) ? $type : 'WebPage';
        return self::finish( [ '@type' => $type ] + $properties );
    }

    public static function article( array $properties, string $type = 'Article' ): array {
        $type = in_array( $type, self::ARTICLE_TYPES, true ) ? $type : 'Article';
        $headline = ValueNormalizer::text( $properties['headline'] ?? $properties['name'] ?? '' );
        $properties['headline'] = function_exists( 'mb_substr' ) ? mb_substr( $headline, 0, 110 ) : substr( $headline, 0, 110 );
        $properties['name'] = $properties['name'] ?? $headline;
        if ( isset( $properties['description'] ) ) {
            $properties['description'] = ValueNormalizer::text( $properties['description'] );
        }
        return self::finish( [ '@type' => $type ] + $properties );
    }

    /**
     * @param array<string,mixed>|string $image URL, or url/contentUrl, width, height, caption, alt, name.
     */
    public static function image( $image, string $id = '' ): array {
        if ( is_string( $image ) ) {
            $image = [ 'url' => $image ];
        }
        if ( ! is_array( $image ) ) {
            return [];
        }
        $url = SchemaGraph::web_url( $image['url'] ?? $image['contentUrl'] ?? $image['full_url'] ?? '' );
        if ( '' === $url ) {
            return [];
        }
        $width  = (int) ( $image['width'] ?? 0 );
        $height = (int) ( $image['height'] ?? 0 );
        return self::finish(
            [
                '@type'                => 'ImageObject',
                '@id'                  => $id,
                'url'                  => $url,
                'contentUrl'           => $url,
                'width'                => $width > 0 ? $width : null,
                'height'               => $height > 0 ? $height : null,
                'name'                 => ValueNormalizer::text( $image['name'] ?? $image['title'] ?? '' ),
                'caption'              => ValueNormalizer::text( $image['caption'] ?? '' ),
                'description'          => ValueNormalizer::text( $image['alt'] ?? $image['description'] ?? '' ),
                'representativeOfPage' => ! empty( $image['representativeOfPage'] ) ? true : null,
            ]
        );
    }

    /** Field row keys: street_address, address_locality, address_region, postal_code, address_country. */
    public static function postal_address( array $row ): array {
        $address = self::finish(
            [
                '@type'           => 'PostalAddress',
                'streetAddress'   => ValueNormalizer::text( $row['streetAddress'] ?? $row['street_address'] ?? '' ),
                'addressLocality' => ValueNormalizer::text( $row['addressLocality'] ?? $row['address_locality'] ?? '' ),
                'addressRegion'   => ValueNormalizer::text( $row['addressRegion'] ?? $row['address_region'] ?? '' ),
                'postalCode'      => ValueNormalizer::text( $row['postalCode'] ?? $row['postal_code'] ?? '' ),
                'addressCountry'  => ValueNormalizer::text( $row['addressCountry'] ?? $row['address_country'] ?? '' ),
            ]
        );
        return count( $address ) > 1 ? $address : [];
    }

    /** Field row keys: name|location, address parts, latitude, longitude, url, same_as|wikipedia_url, email, telephone. */
    public static function place( array $row, string $id = '' ): array {
        $address = self::postal_address( $row );
        $latitude  = ValueNormalizer::number( $row['latitude'] ?? null );
        $longitude = ValueNormalizer::number( $row['longitude'] ?? null );
        $name = ValueNormalizer::text( $row['name'] ?? $row['location'] ?? '' );
        if ( '' === $name && isset( $address['addressLocality'] ) ) {
            $name = (string) $address['addressLocality'];
        }
        if ( '' === $name && [] === $address ) {
            return [];
        }
        return self::finish(
            [
                '@type'     => 'Place',
                '@id'       => $id,
                'name'      => $name,
                'address'   => $address,
                'geo'       => null !== $latitude && null !== $longitude ? [ '@type' => 'GeoCoordinates', 'latitude' => $latitude, 'longitude' => $longitude ] : null,
                'url'       => ValueNormalizer::url( $row['url'] ?? '' ),
                'sameAs'    => ValueNormalizer::url( $row['sameAs'] ?? $row['same_as'] ?? $row['wikipedia_url'] ?? '' ),
                'email'     => ValueNormalizer::email( $row['email'] ?? '' ),
                'telephone' => ValueNormalizer::text( $row['telephone'] ?? '' ),
            ]
        );
    }

    /** Field row keys: contact_type, email, telephone, url, product_supported, hours_available, available_languages. */
    public static function contact_point( array $row ): array {
        $node = self::finish(
            [
                '@type'             => 'ContactPoint',
                'contactType'       => ValueNormalizer::text( $row['contactType'] ?? $row['contact_type'] ?? '' ),
                'email'             => ValueNormalizer::email( $row['email'] ?? '' ),
                'telephone'         => ValueNormalizer::text( $row['telephone'] ?? '' ),
                'url'               => ValueNormalizer::url( $row['url'] ?? '' ),
                'productSupported'  => ValueNormalizer::text( $row['product_supported'] ?? '' ),
                'hoursAvailable'    => ValueNormalizer::text( $row['hours_available'] ?? '' ),
                'availableLanguage' => ValueNormalizer::strings( $row['available_languages'] ?? '' ),
            ]
        );
        return count( $node ) > 1 ? $node : [];
    }

    /** One administrative area or place served. Field row keys: type, name, same_as. */
    public static function area( array $row ): array {
        $type = ValueNormalizer::text( $row['type'] ?? 'Place' );
        $type = in_array( $type, [ 'Country', 'State', 'City', 'AdministrativeArea', 'Place' ], true ) ? $type : 'Place';
        $name = ValueNormalizer::text( $row['name'] ?? '' );
        return '' === $name ? [] : self::finish( [ '@type' => $type, 'name' => $name, 'sameAs' => ValueNormalizer::url( $row['same_as'] ?? '' ) ] );
    }

    /** A PropertyValue identifier, such as a Google Knowledge Graph ID. */
    public static function property_value( string $property_id, $value, string $url = '' ): array {
        $value = ValueNormalizer::text( $value );
        if ( '' === $value ) {
            return [];
        }
        return self::finish( [ '@type' => 'PropertyValue', 'propertyID' => ValueNormalizer::text( $property_id ), 'value' => $value, 'url' => ValueNormalizer::url( $url ) ] );
    }

    /**
     * @param list<array{name:string,url:string}> $items In order, home first.
     */
    public static function breadcrumb( array $items, string $id = '' ): array {
        $elements = [];
        foreach ( $items as $item ) {
            $name = ValueNormalizer::text( $item['name'] ?? '' );
            $url  = SchemaGraph::web_url( $item['url'] ?? '' );
            if ( '' === $name || '' === $url ) {
                continue;
            }
            $elements[] = [ '@type' => 'ListItem', 'position' => count( $elements ) + 1, 'name' => $name, 'item' => $url ];
        }
        return count( $elements ) < 2 ? [] : self::finish( [ '@type' => 'BreadcrumbList', '@id' => $id, 'itemListElement' => $elements ] );
    }

    /**
     * @param list<array<string,mixed>> $items Each a typed node or url/name pair.
     */
    public static function item_list( array $items, string $id = '', string $order = '' ): array {
        $elements = [];
        foreach ( $items as $item ) {
            if ( ! is_array( $item ) || [] === $item ) {
                continue;
            }
            $entry = [ '@type' => 'ListItem', 'position' => count( $elements ) + 1 ];
            if ( isset( $item['@type'] ) ) {
                $entry['url']  = $item['url'] ?? '';
                $entry['name'] = $item['name'] ?? $item['headline'] ?? '';
                $entry['item'] = $item;
            } else {
                $entry['url']  = $item['url'] ?? '';
                $entry['name'] = $item['name'] ?? '';
            }
            $elements[] = self::finish( $entry );
        }
        if ( [] === $elements ) {
            return [];
        }
        $order = '' !== $order ? 'https://schema.org/ItemListOrder' . ucfirst( $order ) : '';
        return self::finish( [ '@type' => 'ItemList', '@id' => $id, 'itemListOrder' => $order, 'numberOfItems' => count( $elements ), 'itemListElement' => $elements ] );
    }

    public static function book( array $properties ): array {
        $properties['image']  = self::image_value( $properties['image'] ?? null );
        $properties['sameAs'] = self::same_as( $properties['sameAs'] ?? [] );
        if ( isset( $properties['bookFormat'] ) && '' !== (string) $properties['bookFormat'] && false === strpos( (string) $properties['bookFormat'], '://' ) ) {
            $properties['bookFormat'] = 'https://schema.org/' . ValueNormalizer::text( $properties['bookFormat'] );
        }
        return self::finish( [ '@type' => 'Book' ] + $properties );
    }

    /** Field row keys: name, description, url, category, sku, brand, area_served, price, price_currency, availability. */
    public static function offer( array $row, string $kind = 'service', string $id = '' ): array {
        $name = ValueNormalizer::text( $row['name'] ?? '' );
        if ( '' === $name ) {
            return [];
        }
        $item_type = 'product' === $kind ? 'Product' : 'Service';
        $availability = ValueNormalizer::text( $row['availability'] ?? '' );
        return self::finish(
            [
                '@type'         => 'Offer',
                '@id'           => $id,
                'url'           => ValueNormalizer::url( $row['url'] ?? '' ),
                'price'         => ValueNormalizer::number( $row['price'] ?? null ),
                'priceCurrency' => strtoupper( ValueNormalizer::text( $row['price_currency'] ?? '' ) ),
                'availability'  => '' !== $availability ? 'https://schema.org/' . $availability : '',
                'itemOffered'   => [
                    '@type'       => $item_type,
                    'name'        => $name,
                    'description' => ValueNormalizer::text( $row['description'] ?? '' ),
                    'url'         => ValueNormalizer::url( $row['url'] ?? '' ),
                    'category'    => ValueNormalizer::text( $row['category'] ?? '' ),
                    'sku'         => 'Product' === $item_type ? ValueNormalizer::text( $row['sku'] ?? '' ) : '',
                    'brand'       => ValueNormalizer::text( $row['brand'] ?? '' ),
                    'areaServed'  => ValueNormalizer::text( $row['area_served'] ?? '' ),
                ],
            ]
        );
    }

    /** Field row keys: name, url, logo (image value). */
    public static function brand( array $row, string $id = '' ): array {
        $name = ValueNormalizer::text( $row['name'] ?? '' );
        return '' === $name ? [] : self::finish( [ '@type' => 'Brand', '@id' => $id, 'name' => $name, 'url' => ValueNormalizer::url( $row['url'] ?? '' ), 'logo' => self::image_value( $row['logo'] ?? null ) ] );
    }

    /** Field row keys: name, category, recognized_by, issued_date, expires_date, url. */
    public static function credential( array $row, string $id = '' ): array {
        $name = ValueNormalizer::text( $row['name'] ?? '' );
        if ( '' === $name ) {
            return [];
        }
        $recognized_by = ValueNormalizer::text( $row['recognized_by'] ?? '' );
        return self::finish(
            [
                '@type'              => 'EducationalOccupationalCredential',
                '@id'                => $id,
                'name'               => $name,
                'credentialCategory' => ValueNormalizer::text( $row['category'] ?? '' ),
                'recognizedBy'       => '' !== $recognized_by ? [ '@type' => ValueNormalizer::text( $row['recognized_by_type'] ?? 'Organization' ) ?: 'Organization', 'name' => $recognized_by ] : null,
                'dateCreated'        => ValueNormalizer::date( $row['issued_date'] ?? '' ),
                'expires'            => ValueNormalizer::date( $row['expires_date'] ?? '' ),
                'url'                => ValueNormalizer::url( $row['url'] ?? '' ),
            ]
        );
    }

    /** Field row keys: name, description, url. */
    public static function demand( array $row, string $id = '' ): array {
        $name = ValueNormalizer::text( $row['name'] ?? '' );
        return '' === $name ? [] : self::finish( [ '@type' => 'Demand', '@id' => $id, 'name' => $name, 'description' => ValueNormalizer::text( $row['description'] ?? '' ), 'url' => ValueNormalizer::url( $row['url'] ?? '' ) ] );
    }

    /** Field row keys: name, date, description, url. */
    public static function event( array $row, string $id = '', array $properties = [] ): array {
        $name = ValueNormalizer::text( $row['name'] ?? '' );
        if ( '' === $name ) {
            return [];
        }
        return self::finish(
            [
                '@type'       => 'Event',
                '@id'         => $id,
                'name'        => $name,
                'startDate'   => ValueNormalizer::date( $row['date'] ?? $row['start_date'] ?? '' ),
                'description' => ValueNormalizer::text( $row['description'] ?? '' ),
                'url'         => ValueNormalizer::url( $row['url'] ?? '' ),
            ] + $properties
        );
    }

    /** A named reference to an entity that has no node of its own on the page. */
    public static function thing( string $name, string $url = '', string $type = 'Thing' ): array {
        $name = ValueNormalizer::text( $name );
        return '' === $name ? [] : self::finish( [ '@type' => '' !== $type ? $type : 'Thing', 'name' => $name, 'url' => ValueNormalizer::url( $url ) ] );
    }

    /**
     * Unique valid web URLs from any mix of strings, URL lists and url rows.
     *
     * @return list<string>
     */
    public static function same_as( ...$values ): array {
        $urls = [];
        foreach ( $values as $value ) {
            foreach ( is_array( $value ) ? $value : [ $value ] as $item ) {
                if ( is_array( $item ) ) {
                    $item = $item['url'] ?? $item['same_as'] ?? '';
                }
                $url = SchemaGraph::web_url( $item );
                if ( '' !== $url ) {
                    $urls[ untrailingslashit( strtolower( $url ) ) ] = $url;
                }
            }
        }
        return array_values( $urls );
    }

    public static function ref( string $id ): array {
        return SchemaGraph::ref( $id );
    }

    /** @param list<array<string,mixed>> $nodes @return list<array{"@id":string}> */
    public static function refs_to( array $nodes ): array {
        $ids = [];
        foreach ( $nodes as $node ) {
            if ( is_array( $node ) && ! empty( $node['@id'] ) ) {
                $ids[] = (string) $node['@id'];
            }
        }
        return SchemaGraph::refs( array_values( array_unique( $ids ) ) );
    }

    /** An image property value: a reference stays a reference, anything else becomes an ImageObject. */
    private static function image_value( $value ) {
        if ( is_array( $value ) && isset( $value['@id'] ) && 1 === count( $value ) ) {
            return $value;
        }
        if ( is_array( $value ) && isset( $value[0] ) ) {
            $images = [];
            foreach ( $value as $item ) {
                $image = self::image_value( $item );
                if ( ! empty( $image ) ) {
                    $images[] = $image;
                }
            }
            return $images;
        }
        if ( is_array( $value ) && isset( $value['@type'], $value['@id'] ) ) {
            return $value;
        }
        return null === $value ? null : self::image( $value );
    }

    /** A person or organization needs a name or an @id to mean anything. */
    private static function named_or_identified( array $properties ): bool {
        return '' !== ValueNormalizer::text( $properties['name'] ?? '' ) || '' !== trim( (string) ( $properties['@id'] ?? '' ) );
    }

    private static function finish( array $node ): array {
        return SchemaGraph::sanitize_urls( SchemaGraph::clean( $node ) );
    }
}
