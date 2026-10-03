<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Block\Adminhtml\Category\Edit\Tab;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Registry;
use Panth\Faq\Block\Adminhtml\Category\Edit\Tab\FaqList;
use Panth\Faq\Model\ResourceModel\Item\Collection;
use Panth\Faq\Model\ResourceModel\Item\CollectionFactory;
use PHPUnit\Framework\TestCase;

class FaqListTest extends TestCase
{
    protected $entity = null;
    protected array $wheres = [];
    protected array $collectionCalls = [];

    protected function blockClass(): string
    {
        return FaqList::class;
    }

    protected function registryKey(): string
    {
        return 'current_category';
    }

    protected function entityGetter(): string
    {
        return 'getCategory';
    }

    protected function table(): string
    {
        return 'panth_faq_item_catalog_category';
    }

    protected function column(): string
    {
        return 'category_id';
    }

    protected function block(): object
    {
        $registry = $this->createStub(Registry::class);
        $registry->method('registry')->willReturnCallback(
            fn ($key) => $key === $this->registryKey() ? $this->entity : null
        );

        $collection = $this->createStub(Collection::class);
        foreach (['addFieldToFilter', 'setOrder', 'setPageSize'] as $method) {
            $collection->method($method)->willReturnCallback(function (...$args) use ($method, $collection) {
                $this->collectionCalls[] = [$method, $args[0], $args[1] ?? null];
                return $collection;
            });
        }
        $collection->method('getItems')->willReturn(['loaded']);
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnCallback(function ($table) use ($select) {
            $this->wheres[] = ['from', $table];
            return $select;
        });
        $select->method('where')->willReturnCallback(function ($cond, $value) use ($select) {
            $this->wheres[] = [$cond, $value];
            return $select;
        });
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchCol')->willReturn(['3', '5']);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnCallback(fn ($name) => 'pfx_' . $name);

        $reflection = new \ReflectionClass($this->blockClass());
        $block = $reflection->newInstanceWithoutConstructor();
        foreach (['registry' => $registry, 'faqCollectionFactory' => $factory, 'resourceConnection' => $resource] as $prop => $value) {
            $property = $reflection->getProperty($prop);
            $property->setValue($block, $value);
        }

        return $block;
    }

    public function testEntityComesFromRegistry(): void
    {
        $this->entity = new DataObject(['id' => 4]);
        $getter = $this->entityGetter();

        $this->assertSame($this->entity, $this->block()->$getter());
    }

    public function testFaqItemsAreActiveSortedAndCapped(): void
    {
        $this->assertSame(['loaded'], $this->block()->getFaqItems());
        $this->assertSame(
            [['addFieldToFilter', 'is_active', 1], ['setOrder', 'question', 'ASC'], ['setPageSize', 100, null]],
            $this->collectionCalls
        );
    }

    public function testSelectedIdsRequireSavedEntity(): void
    {
        $this->assertSame([], $this->block()->getSelectedFaqIds());

        $this->entity = new DataObject(['id' => 0]);
        $this->assertSame([], $this->block()->getSelectedFaqIds());
        $this->assertSame([], $this->wheres);
    }

    public function testSelectedIdsAreReadFromJunctionTable(): void
    {
        $this->entity = new DataObject(['id' => 4]);

        $this->assertSame([3, 5], $this->block()->getSelectedFaqIds());
        $this->assertSame([['from', 'pfx_' . $this->table()], [$this->column() . ' = ?', 4]], $this->wheres);
    }
}
