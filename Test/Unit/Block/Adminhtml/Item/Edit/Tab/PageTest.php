<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Block\Adminhtml\Item\Edit\Tab;

use Magento\Framework\App\Request\Http;
use Magento\Framework\DataObject;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Panth\Faq\Block\Adminhtml\Item\Edit\Tab\Page;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PageTest extends TestCase
{
    protected $item = null;
    protected $posted = null;
    protected array $urls = [];

    protected function blockClass(): string
    {
        return Page::class;
    }

    protected function key(): string
    {
        return 'pages';
    }

    protected function selectedMethod(): string
    {
        return 'getSelectedPages';
    }

    protected function gridRoute(): string
    {
        return 'faq/item/pagesgrid';
    }

    protected function block(): object
    {
        $registry = $this->createStub(Registry::class);
        $registry->method('registry')->willReturnCallback(fn ($key) => $key === 'panth_faq_item' ? $this->item : null);
        $request = $this->createStub(Http::class);
        $request->method('getPost')->willReturnCallback(
            fn ($key, $default = null) => $key === $this->key() ? $this->posted : $default
        );
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(function ($route, $params) {
            $this->urls[] = [$route, $params];
            return '/admin/' . $route;
        });

        $reflection = new \ReflectionClass($this->blockClass());
        $block = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('registry')->setValue($block, $registry);
        $reflection->getProperty('_request')->setValue($block, $request);
        $reflection->getProperty('_urlBuilder')->setValue($block, $url);

        return $block;
    }

    protected function selected(): array
    {
        $block = $this->block();

        return (new \ReflectionMethod($block, $this->selectedMethod()))->invoke($block);
    }

    public function testPostedSelectionWins(): void
    {
        $this->posted = ['4', 'x', '6'];
        $this->item = new DataObject(['id' => 1, $this->key() => [9]]);

        $this->assertSame([0 => 4, 2 => 6], $this->selected());

        $this->posted = 'not-an-array';
        $this->assertSame([], $this->selected());
    }

    public static function storedProvider(): array
    {
        return [
            'json' => ['[2,"3",0]', [2, 3]],
            'comma list' => ['5,,6', [0 => 5, 2 => 6]],
            'array' => [['7', '0', 8], [0 => 7, 2 => 8]],
            'empty string' => ['', []],
            'null' => [null, []],
            'number' => [11, []],
        ];
    }

    #[DataProvider('storedProvider')]
    public function testStoredSelection($stored, array $expected): void
    {
        $this->item = new DataObject(['id' => 1, $this->key() => $stored]);

        $this->assertSame($expected, $this->selected());
    }

    public function testNoItemMeansNoSelection(): void
    {
        $this->assertSame([], $this->selected());
    }

    public function testGridUrlAndTabFlags(): void
    {
        $block = $this->block();

        $this->assertSame('/admin/' . $this->gridRoute(), $block->getGridUrl());
        $this->assertSame([[$this->gridRoute(), ['_current' => true]]], $this->urls);
        $this->assertTrue($block->canShowTab());
        $this->assertFalse($block->isHidden());
    }
}
