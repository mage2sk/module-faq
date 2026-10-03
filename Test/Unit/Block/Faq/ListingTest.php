<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Block\Faq;

use Panth\Faq\Block\Faq\Listing;
use Panth\Faq\Model\ResourceModel\Category\Collection as CategoryCollection;
use Panth\Faq\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Panth\Faq\Test\Unit\Block\BlockTestTrait;
use PHPUnit\Framework\TestCase;

class ListingTest extends TestCase
{
    use BlockTestTrait;

    private int $categoryCreates = 0;
    private array $categoryCalls = [];

    private function block(): Listing
    {
        $collection = $this->createStub(CategoryCollection::class);
        $collection->method('addFieldToFilter')->willReturnCallback(function ($field, $value) use ($collection) {
            $this->categoryCalls[] = ['addFieldToFilter', $field, $value];
            return $collection;
        });
        $collection->method('setOrder')->willReturnCallback(function ($field, $dir) use ($collection) {
            $this->categoryCalls[] = ['setOrder', $field, $dir];
            return $collection;
        });
        $factory = $this->createStub(CategoryCollectionFactory::class);
        $factory->method('create')->willReturnCallback(function () use ($collection) {
            $this->categoryCreates++;
            return $collection;
        });

        return new Listing($this->templateContext(), $factory, $this->itemCollectionFactory(), $this->faqHelper());
    }

    public function testCategoriesAreActiveSortedAndCached(): void
    {
        $block = $this->block();

        $this->assertSame($block->getCategories(), $block->getCategories());
        $this->assertSame(1, $this->categoryCreates);
        $this->assertSame(
            [['addFieldToFilter', 'is_active', 1], ['setOrder', 'sort_order', 'ASC']],
            $this->categoryCalls
        );
    }

    public function testCategoryItemsAndHasItems(): void
    {
        $this->collectionItems[1] = [$this->entity(1)];
        $block = $this->block();

        $block->getCategoryItems(4);
        $this->assertSame(
            [['addFieldToFilter', 'category_id', 4], ['addFieldToFilter', 'is_active', 1], ['setOrder', 'sort_order', 'ASC']],
            $this->collectionCalls[0]
        );
        $this->assertTrue($block->hasItems(4));
        $this->assertFalse($block->hasItems(5));
    }

    public function testAccordionIdsAndIdentities(): void
    {
        $block = $this->block();

        $this->assertSame('faq-item-3-9', $block->getItemAccordionId(3, 9));
        $this->assertSame('faq-category-3', $block->getCategoryAccordionId(3));
        $this->assertSame(['panth_faq_item', 'panth_faq_category'], $block->getIdentities());
    }

    public function testEscapeHtmlAttrUsesContextEscaper(): void
    {
        $context = $this->templateContext();
        $escaper = $this->createMock(\Magento\Framework\Escaper::class);
        $escaper->expects($this->once())->method('escapeHtmlAttr')->with('a"b', false)->willReturn('escaped');
        $context->method('getEscaper')->willReturn($escaper);
        $block = new Listing(
            $context,
            $this->createStub(CategoryCollectionFactory::class),
            $this->itemCollectionFactory(),
            $this->faqHelper()
        );

        $this->assertSame('escaped', $block->escapeHtmlAttr('a"b', false));
    }
}
