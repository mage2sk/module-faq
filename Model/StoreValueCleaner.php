<?php
declare(strict_types=1);

namespace Panth\Faq\Model;

use Magento\Framework\App\ResourceConnection;
use Panth\Faq\Model\ResourceModel\Category as CategoryResource;
use Panth\Faq\Model\ResourceModel\Item as ItemResource;
use Panth\Faq\Model\ResourceModel\StoreValueRow;

// Removes store view values that only repeat the base row. Values that differ
// from the base row are kept: they cannot be told apart from a real override.
class StoreValueCleaner
{
    private const BATCH_SIZE = 500;

    private const ENTITIES = [
        'item' => [
            'value_table' => ItemResource::ITEM_VALUE_TABLE,
            'base_table' => 'panth_faq_item',
            'id_field' => 'item_id',
            'fields' => ItemResource::SCOPED_FIELDS,
        ],
        'category' => [
            'value_table' => CategoryResource::CATEGORY_VALUE_TABLE,
            'base_table' => 'panth_faq_category',
            'id_field' => 'category_id',
            'fields' => CategoryResource::SCOPED_FIELDS,
        ],
    ];

    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    public function entityTypes(): array
    {
        return array_keys(self::ENTITIES);
    }

    // Returns ['rows' => scanned, 'deleted' => rows removed, 'updated' => rows trimmed, 'values' => values cleared].
    public function clean(string $entity, bool $dryRun): array
    {
        $config = self::ENTITIES[$entity] ?? null;
        if ($config === null) {
            throw new \InvalidArgumentException('Unknown FAQ entity type: ' . $entity);
        }
        $stats = ['rows' => 0, 'deleted' => 0, 'updated' => 0, 'values' => 0];
        $lastId = 0;
        while (($batch = $this->fetchBatch($config, $lastId)) !== []) {
            foreach ($batch as $row) {
                $lastId = (int)$row['value_id'];
                $stats['rows']++;
                $this->cleanRow($config, $row, $dryRun, $stats);
            }
        }
        return $stats;
    }

    private function cleanRow(array $config, array $row, bool $dryRun, array &$stats): void
    {
        $fields = $config['fields'];
        $current = [];
        $defaults = [];
        foreach ($fields as $field) {
            $current[$field] = $row[$field];
            $defaults[$field] = $row['base_' . $field];
        }
        $cleaned = StoreValueRow::build($fields, $current, $defaults, []);
        $changed = array_keys(array_diff_assoc(
            array_map([$this, 'key'], $current),
            array_map([$this, 'key'], $cleaned)
        ));
        if ($changed === []) {
            return;
        }
        $stats['values'] += count($changed);
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName($config['value_table']);
        $where = ['value_id = ?' => (int)$row['value_id']];

        if (StoreValueRow::inheritsEverything($cleaned)) {
            $stats['deleted']++;
            if (!$dryRun) {
                $connection->delete($table, $where);
            }
            return;
        }
        $stats['updated']++;
        if (!$dryRun) {
            $connection->update($table, array_intersect_key($cleaned, array_flip($changed)), $where);
        }
    }

    private function fetchBatch(array $config, int $afterId): array
    {
        $connection = $this->resource->getConnection();
        $baseColumns = [];
        foreach ($config['fields'] as $field) {
            $baseColumns['base_' . $field] = 'b.' . $field;
        }
        $select = $connection->select()
            ->from(['v' => $this->resource->getTableName($config['value_table'])], array_merge(['value_id'], $config['fields']))
            ->join(
                ['b' => $this->resource->getTableName($config['base_table'])],
                'b.' . $config['id_field'] . ' = v.' . $config['id_field'],
                $baseColumns
            )
            ->where('v.value_id > ?', $afterId)
            ->order('v.value_id ASC')
            ->limit(self::BATCH_SIZE);
        return $connection->fetchAll($select);
    }

    private function key($value): string
    {
        return $value === null ? "\0null" : (string)$value;
    }
}
