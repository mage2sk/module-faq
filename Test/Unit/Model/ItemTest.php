<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Model;

use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Panth\Faq\Model\Item;
use Panth\Faq\Model\ResourceModel\Item as ItemResource;
use Panth\Faq\Model\ResourceModel\Item\Collection;
use PHPUnit\Framework\TestCase;

class ItemTest extends TestCase
{
    private function item(?Collection $collection = null): Item
    {
        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(ManagerInterface::class));

        $resource = $this->createStub(ItemResource::class);
        $resource->method('getIdFieldName')->willReturn('item_id');

        return new class ($context, $this->createStub(Registry::class), $resource, $collection) extends Item {
            public function __construct($context, $registry, $resource, private $testCollection)
            {
                parent::__construct($context, $registry, $resource);
            }

            public function getCollection()
            {
                return $this->testCollection;
            }
        };
    }

    private function collection(int $size, array &$filters): Collection
    {
        $collection = $this->createStub(Collection::class);
        $collection->method('addFieldToFilter')->willReturnCallback(
            function ($field, $condition) use (&$filters, $collection) {
                $filters[] = [$field, $condition];
                return $collection;
            }
        );
        $collection->method('getSize')->willReturn($size);

        return $collection;
    }

    public function testIdentitiesIncludeOwnTagsOnly(): void
    {
        $item = $this->item();
        $item->setId(7);

        $this->assertSame(['panth_faq_item_7', 'panth_faq_item'], $item->getIdentities());
    }

    public function testIdentitiesIncludeCurrentAndOriginalRelations(): void
    {
        $item = $this->item();
        $item->setId(4);
        $item->setData('products', '[11,"12"]');
        $item->setData('catalog_categories', '3,0,abc,5');
        $item->setData('pages', [8, -1, ['nested']]);
        $item->setOrigData('products', [12, 13]);
        $item->setOrigData('pages', '');

        $this->assertSame(
            [
                'panth_faq_item_4',
                'panth_faq_item',
                'cat_p_11',
                'cat_p_12',
                'cat_p_13',
                'cat_c_3',
                'cat_c_5',
                'cms_p_8',
            ],
            $item->getIdentities()
        );
    }

    public function testBeforeSaveGeneratesUrlKeyFromQuestion(): void
    {
        $filters = [];
        $item = $this->item($this->collection(0, $filters));
        $item->setQuestion('  How do I return an Item?? ');

        $item->beforeSave();

        $this->assertSame('how-do-i-return-an-item', $item->getUrlKey());
        $this->assertSame([['url_key', 'how-do-i-return-an-item']], $filters);
    }

    public function testBeforeSaveKeepsExplicitUrlKeyAndExcludesSelf(): void
    {
        $filters = [];
        $item = $this->item($this->collection(0, $filters));
        $item->setId(9);
        $item->setQuestion('Other');
        $item->setUrlKey('custom-key');

        $item->beforeSave();

        $this->assertSame('custom-key', $item->getUrlKey());
        $this->assertSame([['url_key', 'custom-key'], ['item_id', ['neq' => 9]]], $filters);
    }

    public function testBeforeSaveRejectsDuplicateUrlKey(): void
    {
        $filters = [];
        $item = $this->item($this->collection(1, $filters));
        $item->setUrlKey('taken');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('The URL key "taken" already exists.');
        $item->beforeSave();
    }

    public function testBeforeSaveWithoutQuestionOrKeySkipsValidation(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->never())->method('addFieldToFilter');
        $item = $this->item($collection);

        $item->beforeSave();

        $this->assertNull($item->getUrlKey());
    }

    public function testSetIdWritesItemIdField(): void
    {
        $item = $this->item();
        $item->setId(15);

        $this->assertSame(15, $item->getData('item_id'));
    }
}
