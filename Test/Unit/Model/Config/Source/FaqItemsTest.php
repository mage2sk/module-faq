<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Model\Config\Source;

use Magento\Framework\DataObject;
use Panth\Faq\Model\Config\Source\FaqItems;
use Panth\Faq\Model\ResourceModel\Item\Collection;
use Panth\Faq\Model\ResourceModel\Item\CollectionFactory;
use PHPUnit\Framework\TestCase;

class FaqItemsTest extends TestCase
{
    public function testListsActiveItemsSortedBySortOrder(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())->method('addActiveFilter')->willReturnSelf();
        $collection->expects($this->once())->method('setOrder')->with('sort_order', 'ASC')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator([
            new DataObject(['id' => 4, 'question' => 'First?']),
            new DataObject(['id' => 9, 'question' => 'Second?']),
        ]));
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $this->assertSame(
            [['value' => 4, 'label' => 'First?'], ['value' => 9, 'label' => 'Second?']],
            (new FaqItems($factory))->toOptionArray()
        );
    }

    public function testEmptyCollectionGivesNoOptions(): void
    {
        $collection = $this->createStub(Collection::class);
        $collection->method('addActiveFilter')->willReturnSelf();
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator([]));
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $this->assertSame([], (new FaqItems($factory))->toOptionArray());
    }
}
