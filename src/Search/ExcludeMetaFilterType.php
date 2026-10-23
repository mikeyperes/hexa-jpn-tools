<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Search;

use Hexa\PluginCore\PublicComponents\ProfileValues;
use Hexa\PluginCore\QueryFilter\QueryFilterSet;
use Hexa\PluginCore\QueryFilter\QueryFilterType;

/**
 * A toggle that hides posts whose custom field holds one value (default '1',
 * a true/false field), such as "Hide kids events" over `kids_event`.
 */
final class ExcludeMetaFilterType extends QueryFilterType
{
    public function controls(): array
    {
        return [QueryFilterSet::CONTROL_TOGGLE];
    }

    public function sources(): array
    {
        return ['posts'];
    }

    public function normalize(array $definition, string $source): ?array
    {
        $metaKey = ProfileValues::meta_key($definition['meta_key'] ?? '');
        if ($metaKey === '') {
            return null;
        }

        return [
            'meta_key' => $metaKey,
            'value' => is_scalar($definition['value'] ?? null) && (string) $definition['value'] !== '' ? (string) $definition['value'] : '1',
        ];
    }

    public function where($database, array $filter, $value, array $context): string
    {
        if ($value !== '1') {
            return '';
        }
        $alias = (string) $context['alias'];

        return $database->prepare(
            'NOT EXISTS (SELECT 1 FROM ' . $database->postmeta . ' ' . $alias . ' WHERE ' . $alias . '.post_id = ' . $context['table'] . '.ID AND ' . $alias . '.meta_key = %s AND ' . $alias . '.meta_value = %s)',
            $filter['meta_key'],
            $filter['value']
        );
    }
}
