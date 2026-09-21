<?php

namespace Hexa\PluginCore\LogFiles;

final class ErrorLogReader {
    public function tail( ErrorLogSource $source, int $lines = 150, int $max_bytes = 524288 ): string {
        if ( ! $source->exists() || ! $source->readable() ) {
            return 'Log file not found or not readable.';
        }

        $lines     = max( 1, $lines );
        $max_bytes = max( 4096, $max_bytes );
        $size      = $source->size();

        if ( $size <= 0 ) {
            return 'Log file is empty.';
        }

        $handle = fopen( $source->path, 'rb' );
        if ( ! $handle ) {
            return 'Log file not readable.';
        }

        $read_bytes = min( $size, $max_bytes );
        if ( $read_bytes < $size ) {
            fseek( $handle, -$read_bytes, SEEK_END );
        }

        $content = stream_get_contents( $handle );
        fclose( $handle );

        if ( false === $content || '' === $content ) {
            return 'Log file is empty.';
        }

        $content_lines = preg_split( "/\r\n|\n|\r/", trim( $content ) );
        if ( false === $content_lines || [] === $content_lines ) {
            return 'Log file is empty.';
        }

        if ( $read_bytes < $size && count( $content_lines ) > 1 ) {
            array_shift( $content_lines );
        }

        return implode( "\n", array_slice( $content_lines, -$lines ) );
    }

    /**
     * @param ErrorLogSource[] $sources
     * @return array<int,array{source:string,line:string,level:string}>
     */
    public function fatal_syntax_entries( array $sources, int $limit = 100 ): array {
        $entries = [];

        foreach ( $sources as $source ) {
            if ( ! $source instanceof ErrorLogSource || ! $source->readable() ) {
                continue;
            }

            $content = $this->tail( $source, max( 1000, $limit * 20 ), 1048576 );
            foreach ( preg_split( "/\r\n|\n|\r/", $content ) ?: [] as $line ) {
                $level = ErrorLogClassifier::level( $line );
                if ( 'fatal' === $level ) {
                    $entries[] = [
                        'source' => $source->label,
                        'line'   => $line,
                        'level'  => $level,
                    ];
                }
            }
        }

        return array_slice( $entries, -1 * $limit );
    }

    public function highlighted_html( string $content ): string {
        $lines = preg_split( "/\r\n|\n|\r/", $content ) ?: [];
        $out   = [];

        foreach ( $lines as $line ) {
            $level = ErrorLogClassifier::level( $line );
            $out[] = '<span class="hpc-log-line level-' . esc_attr( $level ) . '">' . esc_html( $line ) . '</span>';
        }

        return implode( "\n", $out );
    }
}
