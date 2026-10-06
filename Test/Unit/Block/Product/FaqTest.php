<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Block\Product;

use Magento\Framework\Registry;
use Panth\Faq\Block\Product\Faq;
use Panth\Faq\Helper\Data as FaqHelper;
use Panth\Faq\Test\Unit\Block\BlockTestTrait;
use PHPUnit\Framework\TestCase;

class FaqTest extends TestCase
{
    use BlockTestTrait;

    private $product = null;

    private function block(): Faq
    {
        $registry = $this->createStub(Registry::class);
        $registry->method('registry')->willReturnCallback(
            fn ($key) => $key === 'current_product' ? $this->product : null
        );

        return new Faq(
            $this->templateContext(),
            $this->itemCollectionFactory(),
            $registry,
            $this->faqHelper(),
            $this->storeManager(2)
        );
    }

    public function testFaqItemsForProductAreFilteredAndLimited(): void
    {
        $this->product = $this->entity(5);
        $this->configValues[FaqHelper::XML_PATH_PRODUCT_LIMIT] = '3';
        $block = $this->block();

        $collection = $block->getFaqItems();

        $this->assertSame($collection, $block->getFaqItems());
        $this->assertSame(
            [
                ['addProductFilter', 5],
                ['addActiveFilter'],
                ['addStoreFilter', 2],
                ['setOrder', 'sort_order', 'ASC'],
                ['setPageSize', 3],
            ],
            $this->collectionCalls[0]
        );
    }

    public function testWithoutProductAnEmptyCollectionIsReturned(): void
    {
        $this->block()->getFaqItems();

        $this->assertSame([], $this->collectionCalls[0]);
    }

    public function testTitleLimitUrlAndEnabledFlag(): void
    {
        $block = $this->block();
        $this->assertSame('Frequently Asked Questions', $block->getTitle());
        $this->assertSame(0, $block->getLimit());
        $this->assertSame('https://shop.test/faq', $block->getFaqUrl());
        $this->assertFalse($block->isEnabled());

        $this->configValues[FaqHelper::XML_PATH_PRODUCT_TITLE] = 'Product questions';
        $this->configValues[FaqHelper::XML_PATH_PRODUCT_LIMIT] = '-4';
        $this->configValues[FaqHelper::XML_PATH_FAQ_ROUTE] = 'help';
        $this->helperFlags['isProductPageEnabled'] = true;
        $this->assertSame('Product questions', $block->getTitle());
        $this->assertSame(0, $block->getLimit());
        $this->assertSame('https://shop.test/help', $block->getFaqUrl());
        $this->assertTrue($block->isEnabled());
        $this->assertInstanceOf(FaqHelper::class, $block->getFaqHelper());
    }

    public function testViewAllLinkNeedsMoreItemsThanLimit(): void
    {
        $this->product = $this->entity(5);
        $this->collectionItems[0] = [$this->entity(1), $this->entity(2), $this->entity(3)];
        $this->configValues[FaqHelper::XML_PATH_PRODUCT_LIMIT] = '2';
        $this->assertTrue($this->block()->shouldShowViewAllLink());

        $this->collections = [];
        $this->configValues[FaqHelper::XML_PATH_PRODUCT_LIMIT] = '3';
        $this->assertFalse($this->block()->shouldShowViewAllLink());

        $this->collections = [];
        $this->configValues[FaqHelper::XML_PATH_PRODUCT_LIMIT] = '0';
        $this->assertFalse($this->block()->shouldShowViewAllLink());
    }

    public function testUncategorizedItemsWithoutProductMatchNothing(): void
    {
        $this->block()->getUncategorizedFaqItems();

        $this->assertSame([['addFieldToFilter', 'main_table.item_id', ['null' => true]]], $this->collectionCalls[0]);
    }

    public function testUncategorizedItemsExcludeAlreadyShownItems(): void
    {
        $this->product = $this->entity(5);
        $this->configValues[FaqHelper::XML_PATH_PRODUCT_LIMIT] = '4';
        $this->collectionItems[1] = [$this->entity(7), $this->entity(8)];

        $this->block()->getUncategorizedFaqItems();

        $this->assertSame(
            [
                ['addProductFilter', 5],
                ['addActiveFilter'],
                ['addStoreFilter', 2],
                ['setOrder', 'sort_order', 'ASC'],
                ['select.joinLeft', ['faq_cat' => 'panth_faq_item_faq_category']],
                ['select.where', 'faq_cat.faq_category_id IS NULL'],
                ['select.group', 'main_table.item_id'],
                ['addFieldToFilter', 'main_table.item_id', ['nin' => [7, 8]]],
                ['setPageSize', 4],
            ],
            $this->collectionCalls[0]
        );
    }

    public function testIdentitiesCombineEntityTagAndItems(): void
    {
        $this->product = $this->entity(5);
        $this->collectionItems[0] = [$this->entity(7)];
        $this->collectionItems[1] = [$this->entity(9)];

        $this->assertSame(['cat_p_5', 'panth_faq_item_7', 'panth_faq_item_9'], $this->block()->getIdentities());
    }

    public function testIdentitiesWithoutProduct(): void
    {
        $this->assertSame([], $this->block()->getIdentities());
    }
}
