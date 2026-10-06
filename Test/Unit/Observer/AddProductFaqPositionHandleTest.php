<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\View\Layout\ProcessorInterface;
use Magento\Framework\View\LayoutInterface;
use Panth\Faq\Helper\Data;
use Panth\Faq\Observer\AddProductFaqPositionHandle;
use PHPUnit\Framework\TestCase;

class AddProductFaqPositionHandleTest extends TestCase
{
    private array $handles = [];

    private function observer(string $action): Observer
    {
        $update = $this->createStub(ProcessorInterface::class);
        $update->method('addHandle')->willReturnCallback(function ($handle) use ($update) {
            $this->handles[] = $handle;
            return $update;
        });
        $layout = $this->createStub(LayoutInterface::class);
        $layout->method('getUpdate')->willReturn($update);

        return new Observer(['full_action_name' => $action, 'layout' => $layout]);
    }

    private function helper(bool $enabled, string $position): Data
    {
        $helper = $this->createStub(Data::class);
        $helper->method('isProductPageEnabled')->willReturn($enabled);
        $helper->method('getProductPosition')->willReturn($position);
        return $helper;
    }

    public function testTabPositionAddsNoHandle(): void
    {
        (new AddProductFaqPositionHandle($this->helper(true, 'tab')))->execute($this->observer('catalog_product_view'));
        $this->assertSame([], $this->handles);
    }

    public function testBelowTabsAddsHandle(): void
    {
        (new AddProductFaqPositionHandle($this->helper(true, 'below_tabs')))
            ->execute($this->observer('catalog_product_view'));
        $this->assertSame(['panth_faq_product_below_tabs'], $this->handles);
    }

    public function testProductInfoAddsHandle(): void
    {
        (new AddProductFaqPositionHandle($this->helper(true, 'product_info')))
            ->execute($this->observer('catalog_product_view'));
        $this->assertSame(['panth_faq_product_product_info'], $this->handles);
    }

    public function testOtherPagesAreIgnored(): void
    {
        (new AddProductFaqPositionHandle($this->helper(true, 'below_tabs')))
            ->execute($this->observer('catalog_category_view'));
        $this->assertSame([], $this->handles);
    }

    public function testDisabledProductFaqAddsNoHandle(): void
    {
        (new AddProductFaqPositionHandle($this->helper(false, 'below_tabs')))
            ->execute($this->observer('catalog_product_view'));
        $this->assertSame([], $this->handles);
    }
}
