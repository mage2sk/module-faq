<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Block\Category;

use Magento\Framework\Registry;
use Panth\Faq\Block\Category\Faq;
use Panth\Faq\Helper\Data as FaqHelper;
use Panth\Faq\Test\Unit\Block\BlockTestTrait;
use PHPUnit\Framework\TestCase;

class FaqTest extends TestCase
{
    use BlockTestTrait;

    private $category = null;

    private function block(): Faq
    {
        $registry = $this->createStub(Registry::class);
        $registry->method('registry')->willReturnCallback(
            fn ($key) => $key === 'current_category' ? $this->category : null
        );

        return new Faq(
            $this->templateContext(),
            $this->itemCollectionFactory(),
            $registry,
            $this->faqHelper(),
            $this->storeManager(3),
            ['name_in_layout' => 'category.faq']
        );
    }

    public function testFaqItemsForCategory(): void
    {
        $this->category = $this->entity(12);
        $this->configValues[FaqHelper::XML_PATH_CATEGORY_LIMIT] = '5';

        $this->block()->getFaqItems();

        $this->assertSame(
            [
                ['addCatalogCategoryFilter', 12],
                ['addActiveFilter'],
                ['addStoreFilter', 3],
                ['setOrder', 'sort_order', 'ASC'],
                ['setPageSize', 5],
            ],
            $this->collectionCalls[0]
        );
    }

    public function testWithoutCategoryNoItemsMatch(): void
    {
        $block = $this->block();
        $block->getFaqItems();

        $this->assertSame([['addFieldToFilter', 'item_id', ['null' => true]]], $this->collectionCalls[0]);
        $this->assertFalse($block->canDisplay());
    }

    public function testCanDisplayRequiresEnabledFlagAndItems(): void
    {
        $this->category = $this->entity(12);
        $this->collectionItems[0] = [$this->entity(1)];
        $block = $this->block();
        $this->assertFalse($block->canDisplay());

        $this->helperFlags['isCategoryPageEnabled'] = true;
        $this->assertTrue($block->canDisplay());
    }

    public function testTitleLimitAndListUrl(): void
    {
        $block = $this->block();
        $this->assertSame('Frequently Asked Questions', $block->getTitle());
        $this->assertSame('https://shop.test/faq', $block->getFaqListUrl());

        $this->configValues[FaqHelper::XML_PATH_CATEGORY_TITLE] = 'Category help';
        $this->configValues[FaqHelper::XML_PATH_CATEGORY_LIMIT] = '7';
        $this->configValues[FaqHelper::XML_PATH_FAQ_ROUTE] = 'support';
        $this->assertSame('Category help', $block->getTitle());
        $this->assertSame(7, $block->getLimit());
        $this->assertSame('https://shop.test/support', $block->getFaqListUrl());
    }

    public function testViewAllLink(): void
    {
        $this->category = $this->entity(12);
        $this->collectionItems[0] = [$this->entity(1), $this->entity(2)];
        $this->configValues[FaqHelper::XML_PATH_CATEGORY_LIMIT] = '1';

        $this->assertTrue($this->block()->shouldShowViewAllLink());
    }

    public function testCacheLifetimeIsOneDay(): void
    {
        $block = $this->block();
        $lifetime = new \ReflectionMethod($block, 'getCacheLifetime');

        $this->assertSame(86400, $lifetime->invoke($block));
    }

    public function testUncategorizedItems(): void
    {
        $this->block()->getUncategorizedFaqItems();
        $this->assertSame([['addFieldToFilter', 'main_table.item_id', ['null' => true]]], $this->collectionCalls[0]);

        $this->collections = [];
        $this->collectionCalls = [];
        $this->category = $this->entity(12);
        $this->block()->getUncategorizedFaqItems();

        $this->assertContains(['select.where', 'faq_cat.faq_category_id IS NULL'], $this->collectionCalls[0]);
        $this->assertSame(['addCatalogCategoryFilter', 12], $this->collectionCalls[0][0]);
        $this->assertNotContains(['setPageSize', 0], $this->collectionCalls[0]);
        $this->assertSame(
            [],
            array_filter($this->collectionCalls[0], static fn ($c) => $c[0] === 'addFieldToFilter')
        );
    }

    public function testIdentities(): void
    {
        $this->category = $this->entity(12);
        $this->collectionItems[0] = [$this->entity(4)];
        $this->collectionItems[1] = [$this->entity(5)];

        $this->assertSame(['cat_c_12', 'panth_faq_item_4', 'panth_faq_item_5'], $this->block()->getIdentities());
    }
}
