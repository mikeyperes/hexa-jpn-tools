<?php

declare(strict_types=1);

function untrailingslashit( string $value ): string { return rtrim( $value, '/' ); }
function wp_strip_all_tags( string $text, bool $breaks = false ): string { return strip_tags( $text ); }
function esc_url_raw( string $url ): string { return $url; }
function sanitize_email( string $email ): string { return $email; }

$root = dirname( __DIR__ );
foreach ( [ 'DataNormalization/ValueNormalizer', 'SchemaTools/SchemaGraph', 'SchemaTools/SchemaNodes' ] as $class ) {
    require $root . '/src/' . $class . '.php';
}

use Hexa\PluginCore\SchemaTools\SchemaNodes;

function person_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

$person = SchemaNodes::person_profile(
    [
        '@id'              => 'https://example.test/#person',
        'url'              => 'https://example.test/',
        'name'             => 'Ada Lovelace',
        'given_name'       => 'Ada',
        'family_name'      => 'Lovelace',
        'alternate_names'  => [ [ 'name' => 'Countess of Lovelace' ], [ 'name' => '' ] ],
        'job_title'        => '<p>Mathematician</p>',
        'description'      => '<p>Wrote the first program.</p>',
        'email'            => 'ada@example.test',
        'birth_date'       => '1815-12-10',
        'birth_place'      => [ 'location' => 'London', 'wikipedia_url' => 'https://en.wikipedia.org/wiki/London' ],
        'nationality'      => [ 'British', [ 'value' => 'English' ] ],
        'languages'        => [ [ 'value' => 'English' ], [ 'value' => 'French' ] ],
        'awards'           => [ 'Royal Medal', [ 'name' => 'Honorary Fellow', 'given_by' => 'Example Society', 'year' => '1840' ] ],
        'images'           => [ 'https://example.test/ada.jpg', [ 'url' => 'https://example.test/ada.jpg' ], 'not a url' ],
        'education'        => [ [ 'school' => 'University of London', 'same_as' => 'https://en.wikipedia.org/wiki/University_of_London', 'degree' => 'B.A.', 'field' => 'Mathematics', 'year' => '1835' ], [ 'school' => '' ] ],
        'occupations'      => [ [ 'name' => 'Mathematician', 'description' => '<b>Analysis</b>' ], [ 'name' => '' ] ],
        'works_for'        => [ [ '@id' => 'https://example.test/org/#organization', '@type' => 'Organization', 'name' => 'Analytical Engines' ], 'bogus' ],
        'same_as'          => [ 'https://www.linkedin.com/in/ada', 'https://www.linkedin.com/in/ada/', 'nope' ],
        'knowledge_graph_id' => '/g/11abc',
        'main_entity_of_page' => 'https://example.test/#webpage',
    ]
);

person_assert( 'Person' === $person['@type'] && 'Ada Lovelace' === $person['name'], 'The Person keeps its type and name.' );
person_assert( 'Countess of Lovelace' === $person['alternateName'], 'One alternate name prints as text; empty rows are dropped.' );
person_assert( 'Mathematician' === $person['jobTitle'] && 'Wrote the first program.' === $person['description'], 'Text fields lose their HTML.' );
person_assert( [ '@type' => 'Place', 'name' => 'London', 'sameAs' => 'https://en.wikipedia.org/wiki/London' ] === $person['birthPlace'], 'The birthplace is a Place linked to its reference page.' );
person_assert( [ 'British', 'English' ] === array_column( $person['nationality'], 'name' ) && 'Country' === $person['nationality'][0]['@type'], 'Nationality is a list of Countries.' );
person_assert( [ 'English', 'French' ] === $person['knowsLanguage'], 'Languages print as a list.' );
person_assert( [ 'Royal Medal', 'Honorary Fellow, Example Society (1840)' ] === $person['award'], 'Awards print as texts.' );
person_assert( 1 === count( $person['image'] ) && 'ImageObject' === $person['image'][0]['@type'], 'Images are deduplicated ImageObjects; invalid URLs are dropped.' );
person_assert( 'CollegeOrUniversity' === $person['alumniOf'][0]['@type'] && 1 === count( $person['alumniOf'] ), 'Education rows become schools; empty rows are dropped.' );
person_assert( 'B.A. in Mathematics' === $person['hasCredential'][0]['name'] && 'University of London' === $person['hasCredential'][0]['recognizedBy']['name'], 'Degrees become credentials recognized by their school.' );
person_assert( [ [ '@type' => 'Occupation', 'name' => 'Mathematician', 'description' => 'Analysis' ] ] === $person['hasOccupation'], 'Occupations keep only named rows.' );
person_assert( 1 === count( $person['worksFor'] ), 'Only node or reference employers are kept.' );
person_assert( [ 'https://www.linkedin.com/in/ada' ] === $person['sameAs'], 'Profile links are valid and deduplicated.' );
person_assert( 'googleKgMID' === $person['identifier']['propertyID'] && '/g/11abc' === $person['identifier']['value'] && str_contains( $person['identifier']['url'], 'kgmid=%2Fg%2F11abc' ), 'The Knowledge Graph ID is a PropertyValue with its Google URL.' );
person_assert( [ '@id' => 'https://example.test/#webpage' ] === $person['mainEntityOfPage'], 'The Person points at the page that describes it.' );
person_assert( [] === SchemaNodes::person_profile( [ 'name' => '' ] ), 'A Person needs a name or an @id.' );

$quote = SchemaNodes::quotation( [ 'text' => 'Imagination is the discovering faculty.', 'creator' => [ '@id' => 'https://example.test/#person' ], 'url' => 'https://example.test/source', 'publication' => 'The Notes' ] );
person_assert( 'Quotation' === $quote['@type'] && 'The Notes' === $quote['citation']['name'], 'A quotation links its creator and source.' );
person_assert( [] === SchemaNodes::quotation( [ 'text' => '' ] ), 'A quotation needs text.' );
person_assert( 'CreativeWork' === SchemaNodes::creative_work( [ 'name' => 'Interview' ], 'Bogus' )['@type'], 'Unknown creative work types fall back to CreativeWork.' );
person_assert( 'EducationalOrganization' === SchemaNodes::educational_organization( [ 'name' => 'X', 'type' => 'Bogus' ] )['@type'], 'Unknown school types fall back to EducationalOrganization.' );

echo "PASS: one person record builds a complete, clean Person profile.\n";
