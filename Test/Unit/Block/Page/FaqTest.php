<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Block\Page;

use Magento\Cms\Model\Page;
use Magento\Framework\App\RequestInterface;
use Panth\Faq\Block\Page\Faq;
use Panth\Faq\Helper\Data as FaqHelper;
use Panth\Faq\Test\Unit\Block\BlockTestTrait;
use PHPUnit\Framework\TestCase;

class FaqTest extends TestCase
{
    use BlockTestTrait;

    private ?int $pageId = null;
    private array $loads = [];

    private function block(): Faq
    {
        $page = $this->createStub(Page::class);
        $page->method('getId')->willReturnCallback(fn () => $this->pageId);
        $page->method('load')->willReturnCallback(function ($id) use ($page) {
            $this->loads[] = $id;
            $this->pageId = (int)$id;
            return $page;
        });
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(fn ($key) => $this->params[$key] ?? null);

        return new Faq(
            $this->templateContext(),
            $this->itemCollectionFactory(),
            $page,
            $request,
            $this->faqHelper(),
            $this->storeManager(4)
        );
    }

    public function testCurrentPageLoadsRequestedPageOnce(): void
    {
        $this->params['page_id'] = '9';
        $block = $this->block();

        $this->assertSame(9, $block->getCurrentPage()->getId());
        $block->getCurrentPage();
        $this->assertSame(['9'], $this->loads);
    }

    public function testCurrentPageIsNullWithoutPage(): void
    {
        $this->assertNull($this->block()->getCurrentPage());
    }

    public function testFaqItemsForPage(): void
    {
        $this->pageId = 3;

        $this->block()->getFaqItems();

        $this->assertSame(
            [['addPageFilter', 3], ['addActiveFilter'], ['addStoreFilter', 4], ['setOrder', 'sort_order', 'ASC']],
            $this->collectionCalls[0]
        );
    }

    public function testFaqItemsWithoutPage(): void
    {
        $block = $this->block();
        $first = $block->getFaqItems();

        $this->assertSame([], $this->collectionCalls[0]);
        $this->assertSame($first, $block->getFaqItems());
    }

    public function testTitleUrlAndFlag(): void
    {
        $block = $this->block();
        $this->assertSame('Frequently Asked Questions', $block->getTitle());
        $this->assertSame('https://shop.test/faq', $block->getFaqUrl());

        $this->configValues[FaqHelper::XML_PATH_CMS_TITLE] = 'Page FAQ';
        $this->helperFlags['isCmsPageEnabled'] = true;
        $this->assertSame('Page FAQ', $block->getTitle());
        $this->assertTrue($block->isEnabled());
    }

    public function testUncategorizedItems(): void
    {
        $this->block()->getUncategorizedFaqItems();
        $this->assertSame([['addFieldToFilter', 'main_table.item_id', ['null' => true]]], $this->collectionCalls[0]);

        $this->collections = [];
        $this->collectionCalls = [];
        $this->pageId = 3;
        $this->collectionItems[1] = [$this->entity(2)];
        $this->block()->getUncategorizedFaqItems();

        $this->assertSame(['addPageFilter', 3], $this->collectionCalls[0][0]);
        $this->assertSame(['addFieldToFilter', 'main_table.item_id', ['nin' => [2]]], end($this->collectionCalls[0]));
    }

    public function testIdentities(): void
    {
        $this->pageId = 3;
        $this->collectionItems[0] = [$this->entity(2)];

        $this->assertSame(['cms_p_3', 'panth_faq_item_2'], $this->block()->getIdentities());
    }
}
