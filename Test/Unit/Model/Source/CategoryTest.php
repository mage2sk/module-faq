<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Model\Source;

use Magento\Framework\DataObject;
use Panth\Faq\Model\ResourceModel\Category\Collection;
use Panth\Faq\Model\ResourceModel\Category\CollectionFactory;
use Panth\Faq\Model\Source\Category;
use PHPUnit\Framework\TestCase;

class CategoryTest extends TestCase
{
    public function testOptionsStartWithPlaceholderAndAreCached(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())->method('addFieldToFilter')->with('is_active', 1)->willReturnSelf();
        $collection->expects($this->once())->method('setOrder')->with('name', 'ASC')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator([
            new DataObject(['id' => 2, 'name' => 'Billing']),
            new DataObject(['id' => 1, 'name' => 'Shipping']),
        ]));
        $factory = $this->createMock(CollectionFactory::class);
        $factory->expects($this->once())->method('create')->willReturn($collection);

        $source = new Category($factory);
        $options = $source->toOptionArray();

        $this->assertCount(3, $options);
        $this->assertSame('', $options[0]['value']);
        $this->assertSame('-- Please Select --', (string)$options[0]['label']);
        $this->assertSame(['value' => 2, 'label' => 'Billing'], $options[1]);
        $this->assertSame(['value' => 1, 'label' => 'Shipping'], $options[2]);
        $this->assertSame($options, $source->toOptionArray());
    }
}
