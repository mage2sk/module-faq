<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Controller\Adminhtml\Item;

use Magento\Framework\App\Request\Http;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\LayoutInterface;
use Magento\Framework\View\Result\Layout;
use Magento\Framework\View\Result\LayoutFactory;
use Panth\Faq\Controller\Adminhtml\Item\PagesGrid;
use Panth\Faq\Controller\Adminhtml\Item\ProductsGrid;
use Panth\Faq\Test\Unit\Controller\ActionContextTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PagesGridTest extends TestCase
{
    use ActionContextTrait;

    public static function gridProvider(): array
    {
        return [
            'pages' => [PagesGrid::class, 'pages', 'setPages', 'faq.item.edit.tab.pages'],
            'products' => [ProductsGrid::class, 'products', 'setProducts', 'faq.item.edit.tab.products'],
        ];
    }

    #[DataProvider('gridProvider')]
    public function testPassesPostedSelectionToGridBlock(
        string $class,
        string $postKey,
        string $setter,
        string $blockName
    ): void {
        $request = $this->createStub(Http::class);
        $request->method('getPost')->willReturnCallback(
            fn ($key, $default = null) => $key === $postKey ? ['4', '7'] : $default
        );
        $block = $this->getMockBuilder(Template::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__call'])
            ->getMock();
        $block->expects($this->once())->method('__call')
            ->with($setter, [['4', '7']])
            ->willReturnSelf();
        $layout = $this->createMock(LayoutInterface::class);
        $layout->expects($this->once())->method('getBlock')->with($blockName)->willReturn($block);
        $result = $this->createStub(Layout::class);
        $result->method('getLayout')->willReturn($layout);
        $factory = $this->createStub(LayoutFactory::class);
        $factory->method('create')->willReturn($result);

        $controller = new $class($this->actionContext(request: $request), $factory);

        $this->assertSame($result, $controller->execute());
        $this->assertSame('Panth_Faq::item_save', $class::ADMIN_RESOURCE);
    }
}
