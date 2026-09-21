<?php

namespace Hexa\PluginCore\WpAdminAjax;

use RuntimeException;

final class AjaxFailure extends RuntimeException {
    private int $status_code;

    private string $error_code;

    private array $extra;

    public function __construct( string $message, int $status_code = 400, string $error_code = 'ajax_error', array $extra = [] ) {
        parent::__construct( $message );

        $this->status_code = $status_code;
        $this->error_code  = $error_code;
        $this->extra       = $extra;
    }

    public static function bad_request( string $message, string $error_code = 'bad_request', array $extra = [] ): self {
        return new self( $message, 400, $error_code, $extra );
    }

    public static function not_found( string $message, string $error_code = 'not_found', array $extra = [] ): self {
        return new self( $message, 404, $error_code, $extra );
    }

    public static function server_error( string $message, string $error_code = 'server_error', array $extra = [] ): self {
        return new self( $message, 500, $error_code, $extra );
    }

    public function status_code(): int {
        return $this->status_code;
    }

    public function payload(): array {
        return array_merge(
            [
                'message' => $this->getMessage(),
                'code'    => $this->error_code,
            ],
            $this->extra
        );
    }
}
