<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Block\Widget;

use Panth\Faq\Block\Widget\Faq;
use Panth\Faq\Test\Unit\Block\BlockTestTrait;
use PHPUnit\Framework\TestCase;

class FaqTest extends TestCase
{
    use BlockTestTrait;

    private function block(array $data = []): Faq
    {
        return new Faq(
            $this->templateContext(),
            $this->itemCollectionFactory(),
            $this->faqHelper(),
            $this->storeManager(2),
            $data
        );
    }

    public function testSelectedItemsKeepConfiguredOrder(): void
    {
        $block = $this->block(['faq_items' => '5, 3,abc,5,0', 'limit' => '2']);

        $collection = $block->getFaqItems();

        $this->assertSame($collection, $block->getFaqItems());
        $calls = $this->collectionCalls[0];
        $this->assertSame(['addFieldToFilter', 'main_table.item_id', ['in' => [5, 3]]], $calls[2]);
        $this->assertSame('select.order', $calls[3][0]);
        $this->assertSame('FIELD(main_table.item_id, 5,3)', (string)$calls[3][1]);
        $this->assertSame(['setPageSize', 2], $calls[4]);
        $this->assertSame(['addStoreFilter', 2], $calls[1]);
    }

    public function testInvalidSelectionFallsBackToSortOrder(): void
    {
        $this->block(['faq_items' => 'x,0'])->getFaqItems();

        $this->assertSame(
            [['addActiveFilter'], ['addStoreFilter', 2], ['setOrder', 'sort_order', 'ASC']],
            $this->collectionCalls[0]
        );
    }

    public function testNoSelectionUsesSortOrder(): void
    {
        $this->block()->getFaqItems();

        $this->assertSame(['setOrder', 'sort_order', 'ASC'], end($this->collectionCalls[0]));
    }

    public function testTitleAndLimit(): void
    {
        $this->assertSame('Frequently Asked Questions', $this->block()->getTitle());
        $this->assertSame('Shipping', $this->block(['title' => 'Shipping'])->getTitle());
        $this->assertSame(0, $this->block(['limit' => '-1'])->getLimit());
        $this->assertSame(6, $this->block(['limit' => '6'])->getLimit());
    }

    public function testShouldDisplay(): void
    {
        $this->assertFalse($this->block()->shouldDisplay());

        $this->helperFlags['isEnabled'] = true;
        $this->assertFalse($this->block()->shouldDisplay());

        $this->collections = [];
        $this->collectionItems[0] = [$this->entity(1)];
        $this->assertTrue($this->block()->shouldDisplay());
    }

    public function testViewAllLinkDefaultsToShown(): void
    {
        $this->assertTrue($this->block()->shouldShowViewAllLink());
        $this->assertTrue($this->block(['show_view_all' => ''])->shouldShowViewAllLink());
        $this->assertFalse($this->block(['show_view_all' => '0'])->shouldShowViewAllLink());
        $this->assertTrue($this->block(['show_view_all' => '1'])->shouldShowViewAllLink());
    }

    public function testFaqUrlAndIdentities(): void
    {
        $this->configValues['panth_faq/general/faq_route'] = 'help';
        $this->collectionItems[0] = [$this->entity(4)];
        $block = $this->block();

        $this->assertSame('https://shop.test/help', $block->getFaqUrl());
        $this->assertSame(['panth_faq_item', 'panth_faq_item_4'], $block->getIdentities());
    }
}
