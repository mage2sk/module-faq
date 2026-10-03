<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Block\Index;

use Panth\Faq\Block\Index\Index;
use Panth\Faq\Helper\Data as FaqHelper;
use Panth\Faq\Model\ResourceModel\Category\Collection as CategoryCollection;
use Panth\Faq\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Panth\Faq\Test\Unit\Block\BlockTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IndexTest extends TestCase
{
    use BlockTestTrait;

    private array $categoryCalls = [];
    private int $categoryCreates = 0;

    private function block(): Index
    {
        $collection = $this->createStub(CategoryCollection::class);
        foreach (['addActiveFilter', 'addStoreFilter', 'setOrder'] as $method) {
            $collection->method($method)->willReturnCallback(function (...$args) use ($method, $collection) {
                $this->categoryCalls[] = [$method, $args[0] ?? null];
                return $collection;
            });
        }
        $factory = $this->createStub(CategoryCollectionFactory::class);
        $factory->method('create')->willReturnCallback(function () use ($collection) {
            $this->categoryCreates++;
            return $collection;
        });

        return new Index(
            $this->templateContext(),
            $factory,
            $this->itemCollectionFactory(),
            $this->faqHelper(),
            $this->storeManager(5),
            $this->scopeConfig()
        );
    }

    public function testCategoriesAreFilteredAndCached(): void
    {
        $block = $this->block();

        $this->assertSame($block->getFaqCategories(), $block->getFaqCategories());
        $this->assertSame(1, $this->categoryCreates);
        $this->assertSame(
            [['addActiveFilter', null], ['addStoreFilter', 5], ['setOrder', 'sort_order']],
            $this->categoryCalls
        );
    }

    public function testItemCollections(): void
    {
        $block = $this->block();
        $block->getFaqItemsByCategory(9);
        $block->getAllFaqItems();
        $block->search('ship');
        $block->getUncategorizedFaqItems();

        $this->assertSame(
            [['addActiveFilter'], ['addStoreFilter', 5], ['addCategoryFilter', 9], ['setOrder', 'sort_order', 'ASC']],
            $this->collectionCalls[0]
        );
        $this->assertSame(
            [['addActiveFilter'], ['addStoreFilter', 5], ['setOrder', 'sort_order', 'ASC']],
            $this->collectionCalls[1]
        );
        $this->assertSame(
            ['addFieldToFilter', ['question', 'answer'], [['like' => '%ship%'], ['like' => '%ship%']]],
            $this->collectionCalls[2][2]
        );
        $this->assertContains(['select.where', 'faq_cat.faq_category_id IS NULL'], $this->collectionCalls[3]);
        $this->assertContains(['select.group', 'main_table.item_id'], $this->collectionCalls[3]);
    }

    public function testRequestDerivedValues(): void
    {
        $block = $this->block();
        $this->assertSame('', $block->getSearchQuery());
        $this->assertSame(0, $block->getSelectedCategory());

        $this->params = ['q' => 'refund', 'category' => '3'];
        $this->assertSame('refund', $block->getSearchQuery());
        $this->assertSame(3, $block->getSelectedCategory());
    }

    public function testUrls(): void
    {
        $block = $this->block();

        $this->assertSame('https://shop.test/faq/index/view?id=4', $block->getFaqItemUrl($this->entity(4)));
        $this->assertSame('https://shop.test/faq/category/view?id=2', $block->getFaqCategoryUrl($this->entity(2)));
        $this->assertSame('https://shop.test/faq', $block->getFaqMainUrl());

        $this->configValues['panth_faq/general/faq_route'] = '/help/';
        $this->assertSame('https://shop.test/help', $block->getFaqMainUrl());
    }

    public static function flagProvider(): array
    {
        return [
            ['isSearchEnabled', FaqHelper::XML_PATH_SHOW_SEARCH],
            ['isCategoryFilterEnabled', FaqHelper::XML_PATH_SHOW_CATEGORY_FILTER],
            ['showCategoryDescription', FaqHelper::XML_PATH_SHOW_CATEGORY_DESC],
            ['showViewCount', FaqHelper::XML_PATH_SHOW_VIEW_COUNT],
            ['isHelpfulVotingEnabled', FaqHelper::XML_PATH_ENABLE_HELPFUL_VOTING],
            ['isDefaultOpen', FaqHelper::XML_PATH_DEFAULT_OPEN_FAQS],
        ];
    }

    #[DataProvider('flagProvider')]
    public function testDisplayFlags(string $method, string $path): void
    {
        $block = $this->block();
        $this->assertFalse($block->$method());
        $this->configValues[$path] = '1';
        $this->assertTrue($block->$method());
    }

    public function testItemsPerPageDefaultsToTwenty(): void
    {
        $block = $this->block();
        $this->assertSame(20, $block->getItemsPerPage());
        $this->configValues[FaqHelper::XML_PATH_ITEMS_PER_PAGE] = '15';
        $this->assertSame(15, $block->getItemsPerPage());
    }

    public function testIdentitiesAreListingTags(): void
    {
        $this->assertSame(['panth_faq_item', 'panth_faq_category'], $this->block()->getIdentities());
    }
}
