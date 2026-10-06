<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Model;

use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Panth\Faq\Model\Category;
use Panth\Faq\Model\ResourceModel\Category as CategoryResource;
use Panth\Faq\Model\ResourceModel\Category\Collection;
use PHPUnit\Framework\TestCase;

class CategoryTest extends TestCase
{
    private function category(?Collection $collection = null): Category
    {
        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(ManagerInterface::class));

        $resource = $this->createStub(CategoryResource::class);
        $resource->method('getIdFieldName')->willReturn('category_id');

        return new class ($context, $this->createStub(Registry::class), $resource, $collection) extends Category {
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

    public function testIdentities(): void
    {
        $category = $this->category();
        $category->setId(12);

        $this->assertSame(['panth_faq_category_12', 'panth_faq_category'], $category->getIdentities());
        $this->assertSame(12, $category->getData('category_id'));
    }

    public function testBeforeSaveGeneratesUrlKeyFromName(): void
    {
        $filters = [];
        $category = $this->category($this->collection(0, $filters));
        $category->setName('Shipping & Delivery / 2024');

        $category->beforeSave();

        $this->assertSame('shipping-delivery-2024', $category->getUrlKey());
        $this->assertSame([['url_key', 'shipping-delivery-2024']], $filters);
    }

    public function testBeforeSaveExcludesCurrentCategoryFromUniquenessCheck(): void
    {
        $filters = [];
        $category = $this->category($this->collection(0, $filters));
        $category->setId(3);
        $category->setUrlKey('returns');

        $category->beforeSave();

        $this->assertSame([['url_key', 'returns'], ['category_id', ['neq' => 3]]], $filters);
    }

    public function testBeforeSaveRejectsDuplicateUrlKey(): void
    {
        $filters = [];
        $category = $this->category($this->collection(2, $filters));
        $category->setName('Returns');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('The URL key "returns" already exists.');
        $category->beforeSave();
    }

    public function testBeforeSaveSkipsValidationWithoutNameOrKey(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->never())->method('addFieldToFilter');

        $category = $this->category($collection);
        $category->beforeSave();

        $this->assertNull($category->getUrlKey());
    }

    public function testBeforeSaveNormalisesTypedUrlKey(): void
    {
        $filters = [];
        $category = $this->category($this->collection(0, $filters));
        $category->setName('Ignored');
        $category->setUrlKey('Shipping & Delivery');

        $category->beforeSave();

        $this->assertSame('shipping-delivery', $category->getUrlKey());
        $this->assertSame([['url_key', 'shipping-delivery']], $filters);
    }
}
