<?php
declare(strict_types=1);

namespace Panth\Faq\Model;

class CacheIdentities
{
    public static function listing(): array
    {
        return [Item::CACHE_TAG, Category::CACHE_TAG];
    }

    public static function fromItems(array $sources, array $extra = []): array
    {
        $identities = $extra;
        foreach ($sources as $items) {
            if (!is_iterable($items)) {
                continue;
            }
            foreach ($items as $item) {
                $id = is_object($item) && method_exists($item, 'getId') ? (int)$item->getId() : 0;
                if ($id > 0) {
                    $identities[] = Item::CACHE_TAG . '_' . $id;
                }
            }
        }

        return array_values(array_unique($identities));
    }
}
