<?php

namespace Hexa\PluginCore\GettingStartedChecklist;

final class ChecklistReportBuilder {
    private function __construct() {}

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<string,mixed>
     */
    public static function table( string $type, string $title, array $items, array $columns, array $args = [] ): array {
        $summary = trim( (string) ( $args['summary'] ?? '' ) );

        return [
            'type'    => self::key( $type ),
            'title'   => '' !== trim( $title ) ? trim( $title ) : 'Checklist Report',
            'summary' => $summary,
            'columns' => self::normalize_columns( $columns ),
            'items'   => array_values( array_filter( array_map( [ self::class, 'normalize_item' ], $items ) ) ),
            'meta'    => self::normalize_meta( is_array( $args['meta'] ?? null ) ? $args['meta'] : [] ),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $files
     * @return array<string,mixed>
     */
    public static function deleted_files( array $files, array $args = [] ): array {
        $items = [];
        foreach ( $files as $file ) {
            if ( ! is_array( $file ) ) {
                continue;
            }

            $path       = self::string( $file['path'] ?? $file['location'] ?? '' );
            $size_bytes = isset( $file['size_bytes'] ) && is_numeric( $file['size_bytes'] ) ? (int) $file['size_bytes'] : null;
            $size       = self::string( $file['size'] ?? '' );
            if ( '' === $size && null !== $size_bytes ) {
                $size = self::format_bytes( $size_bytes );
            }

            $items[] = [
                'file'       => self::string( $file['file'] ?? $file['name'] ?? ( '' !== $path ? basename( $path ) : '' ) ),
                'before'     => self::string( $file['before'] ?? ( '' !== $size ? 'File existed before cleanup; size was ' . $size . '.' : 'File existed before cleanup.' ) ),
                'after'      => self::string( $file['after'] ?? 'Deleted and verified removed after cleanup.' ),
                'location'   => $path,
                'size'       => $size,
                'size_bytes' => null !== $size_bytes ? (string) $size_bytes : '',
            ];
        }

        $count = count( $items );

        return self::table(
            'deleted_files',
            self::string( $args['title'] ?? 'Deleted Files' ),
            $items,
            [
                'file'     => 'File',
                'before'   => 'Before Action',
                'after'    => 'Verified After',
                'location' => 'Location',
                'size'     => 'Deleted Size',
            ],
            [
                'summary' => self::string( $args['summary'] ?? ( $count . ' file' . ( 1 === $count ? '' : 's' ) . ' deleted.' ) ),
                'meta'    => array_merge(
                    [
                        'documentation' => self::string( $args['documentation'] ?? 'This report shows which files existed before cleanup, what the cleanup action did, and what was verified afterward.' ),
                        'summary_items' => [
                            [
                                'label' => 'Before',
                                'value' => $count > 0 ? $count . ' writable file' . ( 1 === $count ? '' : 's' ) . ' existed and matched cleanup rules.' : 'No writable matching files were available to delete.',
                            ],
                            [
                                'label' => 'After',
                                'value' => $count > 0 ? $count . ' file' . ( 1 === $count ? '' : 's' ) . ' deleted and reported below.' : 'No deletion was needed.',
                            ],
                        ],
                        'deleted_count' => $count,
                    ],
                    is_array( $args['meta'] ?? null ) ? $args['meta'] : []
                ),
            ]
        );
    }

    /**
     * @param array<int,array<string,mixed>> $changes
     * @return array<string,mixed>
     */
    public static function wp_config_changes( array $changes, array $args = [] ): array {
        $items = [];
        foreach ( $changes as $change ) {
            if ( ! is_array( $change ) ) {
                continue;
            }

            $items[] = [
                'setting' => self::string( $change['setting'] ?? $change['constant'] ?? $change['name'] ?? '' ),
                'before'  => self::string( $change['before'] ?? $change['old_value'] ?? $change['old'] ?? '' ),
                'after'   => self::string( $change['after'] ?? $change['new_value'] ?? $change['new'] ?? '' ),
                'actual'  => self::string( $change['actual'] ?? $change['actual_value'] ?? '' ),
                'meaning' => self::string( $change['meaning'] ?? self::wp_config_change_meaning( $change ) ),
                'file'    => self::string( $change['file'] ?? $change['path'] ?? 'wp-config.php' ),
            ];
        }

        $count = count( $items );

        return self::table(
            'wp_config_changes',
            self::string( $args['title'] ?? 'wp-config.php Changes' ),
            $items,
            [
                'setting' => 'Setting',
                'before'  => 'Before Action',
                'after'   => 'Requested Value',
                'actual'  => 'Verified After',
                'meaning' => 'What Changed',
                'file'    => 'File',
            ],
            [
                'summary' => self::string( $args['summary'] ?? ( $count . ' wp-config.php setting' . ( 1 === $count ? '' : 's' ) . ' reported.' ) ),
                'meta'    => array_merge(
                    [
                        'documentation' => self::string( $args['documentation'] ?? 'Each row is read before the action, written to the requested value, then read again from wp-config.php to prove the verified after value.' ),
                        'summary_items' => self::wp_config_summary_items( $items ),
                        'change_count'  => $count,
                    ],
                    is_array( $args['meta'] ?? null ) ? $args['meta'] : []
                ),
            ]
        );
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    public static function before_after( string $title, array $rows, array $args = [] ): array {
        return self::table(
            self::string( $args['type'] ?? 'before_after' ),
            $title,
            $rows,
            [
                'item'    => 'Item',
                'before'  => 'Before Action',
                'action'  => 'Action Taken',
                'after'   => 'Verified After',
                'meaning' => 'What Changed',
            ],
            $args
        );
    }

    /**
     * @param array<int,array<string,mixed>> $posts
     * @return array<string,mixed>
     */
    public static function deleted_posts( array $posts, array $args = [] ): array {
        $threshold   = max( 1, (int) ( $args['detail_threshold'] ?? 10 ) );
        $total       = isset( $args['total'] ) ? max( 0, (int) $args['total'] ) : count( $posts );
        $show_rows   = $total <= $threshold;
        $items       = [];
        $media_total = 0;

        if ( $show_rows ) {
            foreach ( $posts as $post ) {
                if ( ! is_array( $post ) ) {
                    continue;
                }

                $media = [];
                foreach ( (array) ( $post['media'] ?? $post['media_files'] ?? [] ) as $media_item ) {
                    if ( is_array( $media_item ) ) {
                        $media[] = self::string( $media_item['url'] ?? $media_item['path'] ?? $media_item['title'] ?? $media_item['id'] ?? '' );
                    } else {
                        $media[] = self::string( $media_item );
                    }
                }
                $media = array_values( array_filter( $media ) );
                $media_total += count( $media );

                $items[] = [
                    'title'     => self::string( $post['title'] ?? $post['post_title'] ?? 'Deleted post' ),
                    'id'        => self::string( $post['id'] ?? $post['ID'] ?? '' ),
                    'permalink' => self::string( $post['permalink'] ?? $post['url'] ?? '' ),
                    'media'     => $media,
                ];
            }
        } else {
            foreach ( $posts as $post ) {
                if ( is_array( $post ) ) {
                    $media_total += count( (array) ( $post['media'] ?? $post['media_files'] ?? [] ) );
                }
            }
        }

        return self::table(
            'deleted_posts',
            self::string( $args['title'] ?? 'Deleted Posts' ),
            $items,
            [
                'title'     => 'Title',
                'id'        => 'ID',
                'permalink' => 'Permalink',
                'media'     => 'Deleted Media',
            ],
            [
                'summary' => self::string( $args['summary'] ?? ( $show_rows ? $total . ' post' . ( 1 === $total ? '' : 's' ) . ' deleted.' : $total . ' posts deleted. Row details hidden because the total is above ' . $threshold . '.' ) ),
                'meta'    => [
                    'deleted_count'    => $total,
                    'media_count'      => $media_total,
                    'detail_threshold' => $threshold,
                    'details_shown'    => $show_rows,
                ],
            ]
        );
    }

    /**
     * @return array<string,mixed>
     */
    public static function confirmation_input( string $id, string $confirm_text, string $label = 'Confirmation', array $args = [] ): array {
        return [
            'id'             => self::key( $id ),
            'label'          => '' !== trim( $label ) ? trim( $label ) : 'Confirmation',
            'type'           => 'confirmation',
            'required'       => true,
            'placeholder'    => self::string( $args['placeholder'] ?? $confirm_text ),
            'description'    => self::string( $args['description'] ?? ( 'Type exactly: ' . $confirm_text ) ),
            'confirm_text'   => $confirm_text,
            'case_sensitive' => (bool) ( $args['case_sensitive'] ?? true ),
        ];
    }

    /**
     * @param array<string,mixed> $columns
     * @return array<int,array<string,string>>
     */
    private static function normalize_columns( array $columns ): array {
        $normalized = [];
        foreach ( $columns as $key => $label ) {
            if ( is_array( $label ) ) {
                $column_key   = self::key( self::string( $label['key'] ?? $key ) );
                $column_label = self::string( $label['label'] ?? $column_key );
            } else {
                $column_key   = self::key( (string) $key );
                $column_label = self::string( $label );
            }

            if ( '' === $column_key ) {
                continue;
            }

            $normalized[] = [ 'key' => $column_key, 'label' => '' !== $column_label ? $column_label : $column_key ];
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $item
     * @return array<string,mixed>
     */
    private static function normalize_item( array $item ): array {
        $normalized = [];
        foreach ( $item as $key => $value ) {
            $normalized[ self::key( (string) $key ) ] = is_array( $value ) ? array_values( array_map( [ self::class, 'string' ], $value ) ) : self::string( $value );
        }

        return array_filter( $normalized, static fn( mixed $value ): bool => [] !== $value && '' !== $value );
    }

    /**
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    private static function normalize_meta( array $meta ): array {
        if ( isset( $meta['summary_items'] ) && is_array( $meta['summary_items'] ) ) {
            $items = [];
            foreach ( $meta['summary_items'] as $item ) {
                if ( is_array( $item ) ) {
                    $items[] = [
                        'label' => self::string( $item['label'] ?? '' ),
                        'value' => self::string( $item['value'] ?? '' ),
                    ];
                } else {
                    $items[] = [ 'label' => '', 'value' => self::string( $item ) ];
                }
            }
            $meta['summary_items'] = array_values(
                array_filter(
                    $items,
                    static fn( array $item ): bool => '' !== $item['label'] || '' !== $item['value']
                )
            );
        }

        return $meta;
    }

    /**
     * @param array<string,mixed> $change
     */
    private static function wp_config_change_meaning( array $change ): string {
        $before = self::string( $change['before'] ?? $change['old_value'] ?? $change['old'] ?? '' );
        $target = self::string( $change['after'] ?? $change['new_value'] ?? $change['new'] ?? '' );
        $actual = self::string( $change['actual'] ?? $change['actual_value'] ?? '' );

        if ( '' === $actual ) {
            return 'Requested ' . $target . '; no verified value was reported.';
        }

        if ( strtolower( $before ) === strtolower( $actual ) ) {
            return 'No effective change; the verified value already matched the requested state.';
        }

        if ( 'undefined' === strtolower( $before ) ) {
            return 'Added the setting and verified it now reads ' . $actual . '.';
        }

        return 'Changed from ' . $before . ' to ' . $actual . '.';
    }

    /**
     * @param array<int,array<string,string>> $items
     * @return array<int,array<string,string>>
     */
    private static function wp_config_summary_items( array $items ): array {
        $summary = [];
        foreach ( $items as $item ) {
            $setting = self::string( $item['setting'] ?? '' );
            if ( '' === $setting ) {
                continue;
            }

            $before = self::string( $item['before'] ?? '' );
            $target = self::string( $item['after'] ?? '' );
            $actual = self::string( $item['actual'] ?? '' );

            $summary[] = [
                'label' => $setting,
                'value' => 'Before: ' . ( '' !== $before ? $before : 'not reported' ) . '; requested: ' . ( '' !== $target ? $target : 'not reported' ) . '; verified after: ' . ( '' !== $actual ? $actual : 'not reported' ) . '.',
            ];
        }

        return $summary;
    }

    private static function key( string $value ): string {
        if ( function_exists( 'sanitize_key' ) ) {
            return sanitize_key( $value );
        }

        return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?: '';
    }

    private static function string( mixed $value ): string {
        if ( is_bool( $value ) ) {
            return $value ? 'true' : 'false';
        }

        if ( is_scalar( $value ) || null === $value ) {
            return trim( (string) $value );
        }

        return wp_json_encode( $value ) ?: '';
    }

    private static function format_bytes( int $bytes ): string {
        if ( function_exists( 'size_format' ) ) {
            return size_format( $bytes );
        }

        if ( $bytes >= 1048576 ) {
            return round( $bytes / 1048576, 2 ) . ' MB';
        }
        if ( $bytes >= 1024 ) {
            return round( $bytes / 1024, 2 ) . ' KB';
        }

        return $bytes . ' B';
    }
}
