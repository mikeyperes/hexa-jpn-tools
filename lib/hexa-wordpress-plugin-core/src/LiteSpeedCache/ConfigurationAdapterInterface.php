<?php

namespace Hexa\PluginCore\LiteSpeedCache;

/** Storage boundary used by the generic profile audit/apply/verify engine. */
interface ConfigurationAdapterInterface {
    public function available(): bool;

    public function litespeed_active(): bool;

    /**
     * @return array{
     *     exists:bool,
     *     stored:mixed,
     *     effective:mixed,
     *     writable:bool,
     *     override_sources:list<string>,
     *     provenance:array<string,mixed>,
     *     error:string
     * }
     */
    public function inspect( SettingDefinition $setting ): array;

    /**
     * Apply a complete service-selected batch. Adapters must not re-audit it.
     *
     * @param list<SettingDefinition> $settings
     */
    public function update( array $settings ): void;
}
