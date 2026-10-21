<?php

namespace Hexa\PluginCore\QueryFilter;

/**
 * One kind of visitor filter over a posts or users query.
 *
 * Extend this class to add a filter type and register it with
 * QueryFilterTypes::register(). A type owns only its definition keys, its SQL
 * condition, and any automatic option source; QueryFilterSet owns the shared
 * definition shape, visitor parsing, the built-in controls (select, toggle,
 * checkboxes, date_range, number_range), several selected values with "any"
 * or "all" matching, and URL arguments. A type may also count how many
 * results carry each option (counts()) and nest its options (depths()).
 */
abstract class QueryFilterType {
    /** Controls this type supports; the first is the default. @return string[] */
    public function controls(): array {
        return [ QueryFilterSet::CONTROL_SELECT ];
    }

    /** Query sources this type supports. @return string[] */
    public function sources(): array {
        return [ 'posts', 'users' ];
    }

    /**
     * Type-specific normalized keys, or null to reject the definition.
     *
     * @param array<string,mixed> $definition Raw host definition.
     * @return array<string,mixed>|null
     */
    public function normalize( array $definition, string $source ): ?array {
        return [];
    }

    /**
     * Keyword `options` values this type resolves itself (for example 'terms').
     * Any other string is treated as a callable, as in DirectorySearch 3.1.0.
     *
     * @return string[]
     */
    public function keywords(): array {
        return [];
    }

    /**
     * Options for one of keywords(), such as 'terms', 'acf', or 'distinct'.
     *
     * @param array<string,mixed> $filter Normalized filter.
     * @param array<string,mixed> $scope  Component scope from QueryFilterSet::scope().
     * @return array<string,string>
     */
    public function keyword_options( array $filter, string $keyword, array $scope ): array {
        return [];
    }

    /**
     * One SQL condition restricting `{$context['table']}.ID`, or '' for no restriction.
     *
     * Identifiers must come from the normalized definition; every visitor value
     * must be bound with $database->prepare().
     *
     * @param object                          $database wpdb-compatible.
     * @param array<string,mixed>             $filter   Normalized filter.
     * QueryFilterSet calls this once per selected value, so a type only ever
     * matches one option; it joins several values with OR ("any") or AND ("all").
     *
     * @param string|array<string,string> $value One parsed option, or a date or number range.
     * @param array<string,mixed>             $context  Scope plus `table` (main alias) and `alias` (unique to this filter).
     */
    abstract public function where( $database, array $filter, $value, array $context ): string;

    /**
     * How many of the given results carry each option, as option value => count,
     * or null when this type cannot count. `$ids_sql` selects the result IDs in
     * a column named `ID`; use QuerySql::in_ids() to restrict by it.
     *
     * @param object              $database wpdb-compatible.
     * @param array<string,mixed> $filter   Normalized filter.
     * @param array<string,mixed> $context  Scope plus a unique `alias`.
     * @return array<string,int>|null
     */
    public function counts( $database, array $filter, string $ids_sql, array $context ): ?array {
        return null;
    }

    /**
     * Nesting depth per option value (0 = top level) for options listed as a
     * tree, such as categories; [] for a flat list. Called after options().
     *
     * @return array<string,int>
     */
    public function depths( array $filter, array $scope ): array {
        return [];
    }

    /** @param object $database @return array{0:string,1:string} Meta table and owner column. */
    protected function meta_table( $database, string $source ): array {
        return 'users' === $source ? [ $database->usermeta, 'user_id' ] : [ $database->postmeta, 'post_id' ];
    }
}
