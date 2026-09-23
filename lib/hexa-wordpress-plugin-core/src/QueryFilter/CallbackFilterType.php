<?php

namespace Hexa\PluginCore\QueryFilter;

/**
 * Host-computed filter: `apply( $value, $profile )` returns the allowed IDs,
 * `null` for no restriction, or `[]` to exclude everything. Works with every
 * control, so hosts can express business rules Core cannot know.
 */
final class CallbackFilterType extends QueryFilterType {
    public function controls(): array {
        return [ QueryFilterSet::CONTROL_SELECT, QueryFilterSet::CONTROL_TOGGLE, QueryFilterSet::CONTROL_DATE_RANGE ];
    }

    public function normalize( array $definition, string $source ): ?array {
        return isset( $definition['apply'] ) && is_callable( $definition['apply'] ) ? [ 'apply' => $definition['apply'] ] : null;
    }

    public function where( $database, array $filter, $value, array $context ): string {
        $ids = call_user_func( $filter['apply'], $value, $context['profile'] ?? [] );
        if ( null === $ids ) {
            return '';
        }

        $ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $ids ), static fn( int $id ): bool => $id > 0 ) ) );

        return [] === $ids ? '1=0' : $context['table'] . '.ID IN (' . implode( ',', $ids ) . ')';
    }
}
