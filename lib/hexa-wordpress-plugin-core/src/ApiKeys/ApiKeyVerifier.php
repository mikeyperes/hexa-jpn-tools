<?php

declare( strict_types=1 );

namespace Hexa\PluginCore\ApiKeys;

/** Tests one service's API key against that service. */
interface ApiKeyVerifier {
    public function verify( string $key ): ApiKeyCheck;
}
