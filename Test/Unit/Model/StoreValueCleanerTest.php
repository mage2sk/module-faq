<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Panth\Faq\Model\ResourceModel\Item as ItemResource;
use Panth\Faq\Model\StoreValueCleaner;
use PHPUnit\Framework\TestCase;

class StoreValueCleanerTest extends TestCase
{
    private array $batches = [];
    private array $writes = [];

    private function cleaner(): StoreValueCleaner
    {
        $select = $this->createStub(Select::class);
        foreach (['from', 'join', 'where', 'order', 'limit'] as $method) {
            $select->method($method)->willReturnSelf();
        }
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturnCallback(fn () => array_shift($this->batches) ?? []);
        foreach (['delete', 'update'] as $method) {
            $connection->method($method)->willReturnCallback(function (...$args) use ($method) {
                $this->writes[] = array_merge([$method], $args);
                return 1;
            });
        }
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        return new StoreValueCleaner($resource);
    }

    private function itemRow(int $valueId, array $store, array $base): array
    {
        $row = ['value_id' => $valueId];
        foreach (ItemResource::SCOPED_FIELDS as $field) {
            $row[$field] = $store[$field] ?? null;
            $row['base_' . $field] = $base[$field] ?? null;
        }
        return $row;
    }

    public function testExactCopiesAreDeletedAndRealOverridesKept(): void
    {
        $base = ['question' => 'Q', 'answer' => 'A', 'is_active' => '1'];
        $this->batches = [[
            $this->itemRow(1, $base, $base),
            $this->itemRow(2, ['question' => 'Other Q', 'answer' => 'A', 'is_active' => '1'], $base),
            $this->itemRow(3, ['question' => 'Other Q'], $base),
        ]];

        $stats = $this->cleaner()->clean('item', false);

        $this->assertSame(['rows' => 3, 'deleted' => 1, 'updated' => 1, 'values' => 5], $stats);
        $this->assertSame([
            ['delete', 'panth_faq_item_value', ['value_id = ?' => 1]],
            ['update', 'panth_faq_item_value', ['answer' => null, 'is_active' => null], ['value_id = ?' => 2]],
        ], $this->writes);
    }

    public function testDryRunWritesNothing(): void
    {
        $base = ['question' => 'Q'];
        $this->batches = [[$this->itemRow(1, $base, $base)]];

        $stats = $this->cleaner()->clean('item', true);

        $this->assertSame(1, $stats['deleted']);
        $this->assertSame([], $this->writes);
    }

    public function testAllBatchesAreRead(): void
    {
        $base = ['question' => 'Q'];
        $this->batches = [[$this->itemRow(1, $base, $base)], [$this->itemRow(2, $base, $base)]];

        $stats = $this->cleaner()->clean('item', false);

        $this->assertSame(2, $stats['rows']);
        $this->assertSame(2, $stats['deleted']);
    }

    public function testUnknownEntityIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->cleaner()->clean('product', true);
    }
}
