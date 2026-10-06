<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Block\Index;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Panth\Faq\Api\CategoryRepositoryInterface;
use Panth\Faq\Api\ItemRepositoryInterface;
use Panth\Faq\Block\Index\View;
use Panth\Faq\Helper\Data as FaqHelper;
use Panth\Faq\Test\Unit\Block\BlockTestTrait;
use Panth\Faq\Test\Unit\EntityTrait;
use PHPUnit\Framework\TestCase;

class ViewTest extends TestCase
{
    use BlockTestTrait;
    use EntityTrait;

    private array $items = [];
    private array $categories = [];
    private array $categoryIds = [];
    private bool $dbFails = false;
    private array $selectFrom = [];

    private function block(): View
    {
        $itemRepository = $this->createStub(ItemRepositoryInterface::class);
        $itemRepository->method('getById')->willReturnCallback(function ($id) {
            if (!isset($this->items[$id])) {
                throw new NoSuchEntityException(__('missing'));
            }
            return $this->items[$id];
        });
        $categoryRepository = $this->createStub(CategoryRepositoryInterface::class);
        $categoryRepository->method('getById')->willReturnCallback(function ($id) {
            if (!isset($this->categories[$id])) {
                throw new NoSuchEntityException(__('missing'));
            }
            return $this->categories[$id];
        });
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnCallback(function ($table, $cols) use ($select) {
            $this->selectFrom = [$table, $cols];
            return $select;
        });
        $select->method('where')->willReturnSelf();
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchCol')->willReturnCallback(function () {
            if ($this->dbFails) {
                throw new \RuntimeException('db');
            }
            return $this->categoryIds;
        });
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);

        return new View(
            $this->templateContext(),
            $this->createStub(Registry::class),
            $itemRepository,
            $categoryRepository,
            $this->faqHelper(),
            $this->scopeConfig(),
            $this->storeManager(1),
            $resource
        );
    }

    public function testFaqItemFromRequest(): void
    {
        $this->assertNull($this->block()->getFaqItem());

        $this->params['id'] = '99';
        $this->assertNull($this->block()->getFaqItem());

        $item = $this->newItem(['item_id' => 4]);
        $this->items[4] = $item;
        $this->params['id'] = '4';
        $this->assertSame($item, $this->block()->getFaqItem());
    }

    public function testFaqCategoriesSkipInactiveAndMissing(): void
    {
        $this->assertSame([], $this->block()->getFaqCategories());

        $this->items[4] = $this->newItem(['item_id' => 4]);
        $this->params['id'] = '4';
        $this->assertSame([], $this->block()->getFaqCategories());

        $active = $this->newCategory(['category_id' => 1, 'is_active' => 1]);
        $this->categories = [1 => $active, 2 => $this->newCategory(['category_id' => 2, 'is_active' => 0])];
        $this->categoryIds = ['1', '2', '3'];

        $this->assertSame([$active], $this->block()->getFaqCategories());
        $this->assertSame(['panth_faq_item_faq_category', ['faq_category_id']], $this->selectFrom);
    }

    public function testFaqCategoriesSwallowDatabaseErrors(): void
    {
        $this->items[4] = $this->newItem(['item_id' => 4]);
        $this->params['id'] = '4';
        $this->dbFails = true;

        $this->assertSame([], $this->block()->getFaqCategories());
    }

    public function testUrls(): void
    {
        $block = $this->block();
        $this->assertSame('https://shop.test/faq', $block->getBackUrl());
        $this->assertSame(
            'https://shop.test/faq/category/shipping',
            $block->getCategoryUrl($this->newCategory(['url_key' => 'shipping']))
        );

        $this->configValues['panth_faq/general/faq_route'] = 'help/';
        $this->assertSame('https://shop.test/help', $block->getBackUrl());
        $this->assertSame('https://shop.test/help', $block->getCategoryUrl($this->newCategory()));
    }

    public function testFlagsAndIdentities(): void
    {
        $block = $this->block();
        $this->assertFalse($block->isHelpfulVotingEnabled());
        $this->assertFalse($block->showViewCount());

        $this->configValues[FaqHelper::XML_PATH_ENABLE_HELPFUL_VOTING] = '1';
        $this->configValues[FaqHelper::XML_PATH_SHOW_VIEW_COUNT] = '1';
        $this->assertTrue($block->isHelpfulVotingEnabled());
        $this->assertTrue($block->showViewCount());
        $this->assertSame(['panth_faq_item', 'panth_faq_category'], $block->getIdentities());
    }
}
