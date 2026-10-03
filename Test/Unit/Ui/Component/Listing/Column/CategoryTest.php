<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Ui\Component\Listing\Column;

use Magento\Framework\DataObject;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Panth\Faq\Model\ResourceModel\Category\Collection;
use Panth\Faq\Model\ResourceModel\Category\CollectionFactory;
use Panth\Faq\Ui\Component\Listing\Column\Category;
use PHPUnit\Framework\TestCase;

class CategoryTest extends TestCase
{
    private function column(int $expectedCreates = 1): Category
    {
        $collection = $this->createStub(Collection::class);
        $collection->method('getIterator')->willReturnCallback(fn () => new \ArrayIterator([
            new DataObject(['id' => 1, 'name' => 'Ship & Co']),
            new DataObject(['id' => 2, 'name' => 'Billing']),
        ]));
        $factory = $this->createMock(CollectionFactory::class);
        $factory->expects($this->exactly($expectedCreates))->method('create')->willReturn($collection);

        return new Category(
            $this->createStub(ContextInterface::class),
            $this->createStub(UiComponentFactory::class),
            $factory
        );
    }

    public function testRendersLabelsForKnownAndUnknownCategories(): void
    {
        $result = $this->column()->prepareDataSource(['data' => ['items' => [
            ['category_id' => '1,2,99'],
            ['category_id' => [2]],
            ['category_id' => 1],
            ['category_id' => ''],
            ['category_id' => '0'],
            ['question' => 'no category key'],
        ]]]);
        $items = $result['data']['items'];

        $this->assertStringContainsString('title="ID: 1 - Ship &amp; Co"', $items[0]['category_id']);
        $this->assertStringContainsString('>Billing</span>', $items[0]['category_id']);
        $this->assertStringContainsString('>ID: 99</span>', $items[0]['category_id']);
        $this->assertSame(3, substr_count($items[0]['category_id'], '<span'));
        $this->assertStringContainsString('>Billing</span>', $items[1]['category_id']);
        $this->assertStringContainsString('>Ship &amp; Co</span>', $items[2]['category_id']);
        foreach ([3, 4, 5] as $index) {
            $this->assertStringContainsString('No category', $items[$index]['category_id']);
        }
    }

    public function testCategoryNamesAreLoadedOnce(): void
    {
        $column = $this->column(1);

        $column->prepareDataSource(['data' => ['items' => [['category_id' => 1]]]]);
        $result = $column->prepareDataSource(['data' => ['items' => [['category_id' => 2]]]]);

        $this->assertStringContainsString('Billing', $result['data']['items'][0]['category_id']);
    }

    public function testNoItemsMeansNoLookup(): void
    {
        $this->assertSame(['data' => []], $this->column(0)->prepareDataSource(['data' => []]));
    }
}
