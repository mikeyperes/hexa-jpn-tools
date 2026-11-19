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

    public const EDUCATIONAL_ORGANIZATION_TYPES = [
        'EducationalOrganization',
        'CollegeOrUniversity',
        'HighSchool',
        'MiddleSchool',
        'ElementarySchool',
        'Preschool',
        'School',
    ];

    public const CREATIVE_WORK_TYPES = [
        'CreativeWork',
        'Article',
        'NewsArticle',
        'BlogPosting',
        'Report',
        'Quotation',
        'Book',
        'Movie',
        'TVSeries',
        'MusicRecording',
        'PodcastEpisode',
        'VideoObject',
        'VisualArtwork',
        'WebContent',
    ];

    public const PAGE_TYPES = [
        'WebPage',
        'AboutPage',
        'ContactPage',
        'ProfilePage',
        'CollectionPage',
        'ImageGallery',
        'ItemPage',
        'SearchResultsPage',
        'FAQPage',
    ];

    /** Any typed node: @type, optional @id, then the given properties. */
    public static function node( string $type, array $properties, string $id = '' ): array {
        return self::finish( [ '@type' => $type, '@id' => $id ] + $properties );
    }

    /** Podcast/media builders accept schema.org properties and derive stable IDs from url. */
    private static function identified_media(string $type, string $fragment, array $properties): array {
        $id = (string) ($properties['@id'] ?? SchemaIds::for_url((string) ($properties['url'] ?? $properties['contentUrl'] ?? ''), $fragment));
        unset($properties['@id']);
        return self::node($type, $properties, $id);
    }
    public static function podcast_series(array $properties): array { return self::identified_media('PodcastSeries', 'podcast-series', $properties); }
    public static function podcast_season(array $properties): array { return self::identified_media('PodcastSeason', 'podcast-season', $properties); }
    public static function podcast_episode(array $properties): array { return self::identified_media('PodcastEpisode', 'podcast-episode', $properties); }
    public static function audio_object(array $properties): array { return self::identified_media('AudioObject', 'audio', $properties); }
    public static function video_object(array $properties): array { return self::identified_media('VideoObject', 'video', $properties); }
    public static function data_feed(array $properties): array { return self::identified_media('DataFeed', 'datafeed', $properties); }

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

    /**
     * An exhibition, show or fair (schema.org ExhibitionEvent).
     * Field row keys: name, start_date, end_date, description, url, venue,
     * city, address, organizer, organizer_url, works (list of names or rows),
     * image. `$properties` adds or overrides properties, e.g. contributor.
     */
    public static function exhibition_event( array $row, string $id = '', array $properties = [] ): array {
        $name = ValueNormalizer::text( $row['name'] ?? '' );
        if ( '' === $name ) {
            return [];
        }
        $venue = ValueNormalizer::text( $row['venue'] ?? '' );
        $location = null;
        if ( '' !== $venue || '' !== ValueNormalizer::text( $row['city'] ?? '' ) ) {
            $location = self::finish(
                [
                    '@type'   => 'Place',
                    'name'    => '' !== $venue ? $venue : ValueNormalizer::text( $row['city'] ?? '' ),
                    'address' => self::finish( [ '@type' => 'PostalAddress', 'streetAddress' => ValueNormalizer::text( $row['address'] ?? '' ), 'addressLocality' => ValueNormalizer::text( $row['city'] ?? '' ) ] ),
                ]
            );
        }
        $works = [];
        foreach ( (array) ( $row['works'] ?? [] ) as $work ) {
            $work_name = is_array( $work ) ? ( $work['name'] ?? $work['value'] ?? '' ) : $work;
            $node = self::thing( (string) $work_name, is_array( $work ) ? (string) ( $work['url'] ?? '' ) : '', 'VisualArtwork' );
            if ( $node ) {
                $works[] = $node;
            }
        }
        $organizer = ValueNormalizer::text( $row['organizer'] ?? '' );
        return self::finish(
            [
                '@type'       => 'ExhibitionEvent',
                '@id'         => $id,
                'name'        => $name,
                'startDate'   => ValueNormalizer::date( $row['start_date'] ?? $row['date'] ?? '' ),
                'endDate'     => ValueNormalizer::date( $row['end_date'] ?? '' ),
                'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
                'description' => ValueNormalizer::text( $row['description'] ?? '' ),
                'url'         => ValueNormalizer::url( $row['url'] ?? '' ),
                'image'       => self::image_value( $row['image'] ?? null ),
                'location'    => $location,
                'organizer'   => '' !== $organizer ? self::thing( $organizer, (string) ( $row['organizer_url'] ?? '' ), 'Organization' ) : null,
                'workFeatured' => $works,
            ] + $properties
        );
    }

    /**
     * One award as the text schema.org's Person/Organization `award` takes
     * (schema.org has no Award type). Field row keys: name, year, given_by.
     * Gives "Best in Show, Palm Beach Art Show (2025)".
     */
    public static function award_text( array $row ): string {
        $name = ValueNormalizer::text( $row['name'] ?? '' );
        if ( '' === $name ) {
            return '';
        }
        $given_by = ValueNormalizer::text( $row['given_by'] ?? '' );
        $year     = ValueNormalizer::text( $row['year'] ?? '' );
        return $name . ( '' !== $given_by ? ', ' . $given_by : '' ) . ( '' !== $year ? ' (' . $year . ')' : '' );
    }

    /** One occupation or role. Field row keys: name, description, url. */
    public static function occupation( array $row ): array {
        $name = ValueNormalizer::text( $row['name'] ?? '' );
        if ( '' === $name ) {
            return [];
        }
        return self::finish(
            [
                '@type'       => 'Occupation',
                'name'        => $name,
                'description' => ValueNormalizer::text( $row['description'] ?? $row['summary'] ?? '' ),
                'url'         => ValueNormalizer::url( $row['url'] ?? '' ),
            ]
        );
    }

    /**
     * A school, college or university. Field row keys: name, url (its own
     * site), same_as (a reference page such as Wikipedia), type.
     */
    public static function educational_organization( array $row ): array {
        $name = ValueNormalizer::text( $row['name'] ?? '' );
        if ( '' === $name ) {
            return [];
        }
        $type = ValueNormalizer::text( $row['type'] ?? 'CollegeOrUniversity' );
        $type = in_array( $type, self::EDUCATIONAL_ORGANIZATION_TYPES, true ) ? $type : 'EducationalOrganization';
        return self::finish(
            [
                '@type'  => $type,
                'name'   => $name,
                'url'    => ValueNormalizer::url( $row['url'] ?? '' ),
                'sameAs' => ValueNormalizer::url( $row['same_as'] ?? '' ),
            ]
        );
    }

    /** A country, for nationality. */
    public static function country( string $name, string $same_as = '' ): array {
        $name = ValueNormalizer::text( $name );
        return '' === $name ? [] : self::finish( [ '@type' => 'Country', 'name' => $name, 'sameAs' => ValueNormalizer::url( $same_as ) ] );
    }

    /**
     * A quotation attributed to someone. Field row keys: text, creator (a
     * node or reference), creator_name, url (the original source),
     * publication (its name), date.
     */
    public static function quotation( array $row, string $id = '' ): array {
        $text = ValueNormalizer::text( $row['text'] ?? '' );
        if ( '' === $text ) {
            return [];
        }
        $creator = is_array( $row['creator'] ?? null ) ? $row['creator'] : null;
        if ( null === $creator && '' !== ValueNormalizer::text( $row['creator_name'] ?? '' ) ) {
            $creator = self::thing( (string) $row['creator_name'], '', 'Person' );
        }
        $publication = ValueNormalizer::text( $row['publication'] ?? '' );
        $url = ValueNormalizer::url( $row['url'] ?? '' );
        return self::finish(
            [
                '@type'        => 'Quotation',
                '@id'          => $id,
                'text'         => $text,
                'creator'      => $creator,
                'datePublished' => ValueNormalizer::date( $row['date'] ?? '' ),
                'citation'     => '' !== $publication || '' !== $url ? self::finish( [ '@type' => 'CreativeWork', 'name' => $publication, 'url' => $url ] ) : null,
                'url'          => $url,
            ]
        );
    }

    /**
     * Any creative work (an interview, a press piece, a podcast appearance).
     * Unknown types fall back to CreativeWork.
     */
    public static function creative_work( array $properties, string $type = 'CreativeWork' ): array {
        if ( ! self::named_or_identified( $properties ) ) {
            return [];
        }
        $type = in_array( $type, self::CREATIVE_WORK_TYPES, true ) ? $type : 'CreativeWork';
        $properties['image']  = self::image_value( $properties['image'] ?? null );
        $properties['sameAs'] = self::same_as( $properties['sameAs'] ?? [] );
        if ( isset( $properties['description'] ) ) {
            $properties['description'] = ValueNormalizer::text( $properties['description'] );
        }
        return self::finish( [ '@type' => $type ] + $properties );
    }

    /**
     * A complete Person for a profile: identity, names, birth, nationality,
     * languages, education, credentials, occupations, awards, images,
     * employers, profile links and the Google Knowledge Graph ID, from one
     * plain record. Any plugin with a person (a person website, an author, a
     * verified profile) maps its fields to these keys and gets the same
     * Person node.
     *
     * Record keys: @id, url, name, given_name, family_name, additional_name,
     * alternate_names (list), honorific_prefix, honorific_suffix, job_title,
     * description, email, telephone, gender, birth_date, birth_place (place
     * row: name|location, same_as|wikipedia_url), nationality (list of names
     * or rows with name, same_as), languages (list), awards (list of texts or
     * award rows), images (list of URLs or image rows), education (rows:
     * school, school_url, same_as, degree, field, year, type), occupations
     * (rows: name, description, url), works_for (list of nodes or
     * references), same_as (list), knowledge_graph_id, main_entity_of_page
     * (a page @id), properties (raw schema.org properties that override).
     */
    public static function person_profile( array $record ): array {
        $education    = ValueNormalizer::rows( $record['education'] ?? [] );
        $alumni       = [];
        $credentials  = [];
        foreach ( $education as $row ) {
            $school = self::educational_organization(
                [
                    'name'    => $row['school'] ?? $row['name'] ?? '',
                    'url'     => $row['school_url'] ?? '',
                    'same_as' => $row['same_as'] ?? $row['wikipedia_url'] ?? '',
                    'type'    => $row['type'] ?? 'CollegeOrUniversity',
                ]
            );
            if ( [] !== $school ) {
                $alumni[] = $school;
            }
            $degree = ValueNormalizer::text( $row['degree'] ?? '' );
            $field  = ValueNormalizer::text( $row['field'] ?? '' );
            if ( '' !== $degree || '' !== $field ) {
                $credential = self::credential(
                    [
                        'name'          => implode( ' in ', array_filter( [ $degree, $field ] ) ),
                        'category'      => '' !== $degree ? 'degree' : '',
                        'recognized_by' => (string) ( $school['name'] ?? '' ),
                        'recognized_by_type' => (string) ( $school['@type'] ?? 'EducationalOrganization' ),
                        'issued_date'   => $row['year'] ?? '',
                    ]
                );
                if ( [] !== $credential ) {
                    $credentials[] = $credential;
                }
            }
        }

        $occupations = [];
        foreach ( ValueNormalizer::rows( $record['occupations'] ?? [] ) as $row ) {
            $occupation = self::occupation( $row );
            if ( [] !== $occupation ) {
                $occupations[] = $occupation;
            }
        }

        $nationality = [];
        foreach ( (array) ( $record['nationality'] ?? [] ) as $item ) {
            $country = is_array( $item ) ? self::country( (string) ( $item['name'] ?? $item['value'] ?? '' ), (string) ( $item['same_as'] ?? '' ) ) : self::country( (string) $item );
            if ( [] !== $country ) {
                $nationality[] = $country;
            }
        }

        $awards = [];
        foreach ( (array) ( $record['awards'] ?? [] ) as $award ) {
            $text = is_array( $award ) ? self::award_text( $award ) : ValueNormalizer::text( $award );
            if ( '' !== $text ) {
                $awards[] = $text;
            }
        }

        $birth_place = is_array( $record['birth_place'] ?? null )
            ? self::place(
                [
                    'name'    => $record['birth_place']['name'] ?? $record['birth_place']['location'] ?? '',
                    'same_as' => $record['birth_place']['same_as'] ?? $record['birth_place']['wikipedia_url'] ?? '',
                ]
            )
            : [];

        $kgmid = ValueNormalizer::text( $record['knowledge_graph_id'] ?? '' );
        $images = [];
        foreach ( (array) ( $record['images'] ?? [] ) as $image ) {
            $node = self::image( $image );
            if ( [] !== $node ) {
                $images[ $node['url'] ] = $node;
            }
        }

        $person = [
            '@id'              => (string) ( $record['@id'] ?? '' ),
            'name'             => ValueNormalizer::text( $record['name'] ?? '' ),
            'url'              => ValueNormalizer::url( $record['url'] ?? '' ),
            'givenName'        => ValueNormalizer::text( $record['given_name'] ?? '' ),
            'familyName'       => ValueNormalizer::text( $record['family_name'] ?? '' ),
            'additionalName'   => ValueNormalizer::text( $record['additional_name'] ?? '' ),
            'alternateName'    => ValueNormalizer::single_or_array( ValueNormalizer::strings( $record['alternate_names'] ?? [], [ 'name', 'value' ] ) ),
            'honorificPrefix'  => ValueNormalizer::text( $record['honorific_prefix'] ?? '' ),
            'honorificSuffix'  => ValueNormalizer::text( $record['honorific_suffix'] ?? '' ),
            'jobTitle'         => ValueNormalizer::text( $record['job_title'] ?? '' ),
            'description'      => ValueNormalizer::text( $record['description'] ?? '' ),
            'email'            => $record['email'] ?? '',
            'telephone'        => ValueNormalizer::text( $record['telephone'] ?? '' ),
            'gender'           => ValueNormalizer::text( $record['gender'] ?? '' ),
            'birthDate'        => ValueNormalizer::date( $record['birth_date'] ?? '' ),
            'birthPlace'       => $birth_place,
            'nationality'      => $nationality,
            'knowsLanguage'    => ValueNormalizer::single_or_array( ValueNormalizer::strings( $record['languages'] ?? [], [ 'value', 'name' ] ) ),
            'alumniOf'         => $alumni,
            'hasCredential'    => $credentials,
            'hasOccupation'    => $occupations,
            'award'            => ValueNormalizer::single_or_array( array_values( array_unique( $awards ) ) ),
            'image'            => array_values( $images ),
            'worksFor'         => array_values( array_filter( (array) ( $record['works_for'] ?? [] ), 'is_array' ) ),
            'sameAs'           => $record['same_as'] ?? [],
            'identifier'       => '' !== $kgmid ? self::property_value( 'googleKgMID', $kgmid, 'https://www.google.com/search?kgmid=' . rawurlencode( $kgmid ) ) : null,
            'mainEntityOfPage' => ! empty( $record['main_entity_of_page'] ) ? self::ref( (string) $record['main_entity_of_page'] ) : null,
        ];
        $properties = is_array( $record['properties'] ?? null ) ? $record['properties'] : [];
        return self::person( $properties + $person );
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
                    $urls[ untrailingslashit( strtolower( $url ) ) ] ??= $url;
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
