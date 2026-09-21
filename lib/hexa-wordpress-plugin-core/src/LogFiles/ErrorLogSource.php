<?php

namespace Hexa\PluginCore\LogFiles;

final class ErrorLogSource {
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $path,
        public readonly bool $deletable = false,
        public readonly string $delete_button_id = ''
    ) {
    }

    public function exists(): bool {
        return is_file( $this->path );
    }

    public function readable(): bool {
        return $this->exists() && is_readable( $this->path );
    }

    public function size(): int {
        return $this->exists() ? (int) filesize( $this->path ) : 0;
    }

    public function size_label(): string {
        if ( ! $this->exists() ) {
            return 'N/A';
        }

        if ( function_exists( 'size_format' ) ) {
            return size_format( $this->size() );
        }

        return number_format( $this->size() ) . ' B';
    }
}
