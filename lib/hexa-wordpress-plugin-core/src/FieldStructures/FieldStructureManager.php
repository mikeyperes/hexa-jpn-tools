<?php

namespace Hexa\PluginCore\FieldStructures;

final class FieldStructureManager {
    public function normalizeDefinitions( array $definitions ): array {
        $items = [];
        foreach ( $definitions as $id => $definition ) {
            if ( ! is_array( $definition ) ) {
                continue;
            }
            if ( ! isset( $definition["id"] ) && is_string( $id ) ) {
                $definition["id"] = $id;
            }
            $items[] = $this->normalizeDefinition( $definition );
        }
        return $items;
    }

    public function summarize( array $definitions ): array {
        $summary = [ "total" => count( $definitions ), "enabled" => 0, "registered" => 0, "warnings" => 0 ];
        foreach ( $definitions as $definition ) {
            if ( ! empty( $definition["enabled"] ) ) {
                $summary["enabled"]++;
            }
            if ( ! empty( $definition["registered"] ) ) {
                $summary["registered"]++;
            }
            if ( ! empty( $definition["enabled"] ) && empty( $definition["registered"] ) ) {
                $summary["warnings"]++;
            }
        }
        return $summary;
    }

    private function normalizeDefinition( array $definition ): array {
        $registered = $definition["registered"] ?? false;
        if ( is_callable( $registered ) ) {
            $registered = (bool) call_user_func( $registered );
        }
        $test_report = $definition["test_report"] ?? "No test report provided.";
        if ( is_callable( $test_report ) ) {
            $test_report = (string) call_user_func( $test_report );
        }
        return [
            "id"           => sanitize_key( (string) ( $definition["id"] ?? "field_structure" ) ),
            "label"        => (string) ( $definition["label"] ?? "Field Structure" ),
            "type"         => sanitize_key( (string) ( $definition["type"] ?? "acf" ) ),
            "description"  => (string) ( $definition["description"] ?? "" ),
            "instructions" => (string) ( $definition["instructions"] ?? "" ),
            "setting_key"  => sanitize_key( (string) ( $definition["setting_key"] ?? "" ) ),
            "enabled"      => (bool) ( $definition["enabled"] ?? false ),
            "registered"   => (bool) $registered,
            "acf_group_key" => (string) ( $definition["acf_group_key"] ?? "" ),
            "object_name"   => (string) ( $definition["object_name"] ?? "" ),
            "location"      => (string) ( $definition["location"] ?? "" ),
            "fields"        => $this->normalizeTextList( $definition["fields"] ?? [] ),
            "dependencies"  => $this->normalizeTextList( $definition["dependencies"] ?? [] ),
            "code_example"  => (string) ( $definition["code_example"] ?? "" ),
            "test_report"   => $test_report,
            "activity"      => (string) ( $definition["activity"] ?? "" ),
            "edit_url"      => (string) ( $definition["edit_url"] ?? "" ),
        ];
    }

    private function normalizeTextList( mixed $items ): array {
        if ( is_string( $items ) ) {
            $items = preg_split( "/\r\n|\r|\n/", $items ) ?: [];
        }
        if ( ! is_array( $items ) ) {
            return [];
        }
        $out = [];
        foreach ( $items as $item ) {
            $item = trim( (string) $item );
            if ( "" !== $item ) {
                $out[] = $item;
            }
        }
        return $out;
    }

    public static function registeredPostType( string $slug ): bool {
        return function_exists( "post_type_exists" ) && post_type_exists( $slug );
    }

    public static function registeredTaxonomy( string $slug ): bool {
        return function_exists( "taxonomy_exists" ) && taxonomy_exists( $slug );
    }

    public static function acfGroupAvailable( string $group_key ): bool {
        if ( ! function_exists( "acf_get_field_group" ) || "" === $group_key ) {
            return false;
        }
        return (bool) acf_get_field_group( $group_key );
    }
}
