<?php

declare( strict_types=1 );

namespace Hexa\PluginCore\ApiKeys;

/** Signs the exact bytes sent in a domain-bound JSON request. */
final class HmacSignature {
    public static function sign( string $method, string $path, string $timestamp, string $nonce, string $domain, string $body, string $key ): string {
        $canonical = implode( "\n", [
            $method,
            $path,
            $timestamp,
            $nonce,
            $domain,
            hash( 'sha256', $body ),
        ] );

        return hash_hmac( 'sha256', $canonical, $key );
    }
}
