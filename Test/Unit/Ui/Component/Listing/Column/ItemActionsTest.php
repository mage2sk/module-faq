<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Panth\Faq\Ui\Component\Listing\Column\CategoryActions;
use Panth\Faq\Ui\Component\Listing\Column\ItemActions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ItemActionsTest extends TestCase
{
    public static function columnProvider(): array
    {
        return [
            'item' => [ItemActions::class, 'item_id', 'faq/item', 'Delete FAQ Item'],
            'category' => [CategoryActions::class, 'category_id', 'faq/category', 'Delete FAQ Category'],
        ];
    }

    #[DataProvider('columnProvider')]
    public function testBuildsEditAndDeleteLinks(string $class, string $idField, string $path, string $title): void
    {
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            fn ($route, $params) => 'https://admin.test/' . $route . '/' . http_build_query($params)
        );
        $column = new $class(
            $this->createStub(ContextInterface::class),
            $this->createStub(UiComponentFactory::class),
            $url,
            [],
            ['name' => 'actions']
        );

        $result = $column->prepareDataSource(['data' => ['items' => [
            [$idField => 5],
            ['other' => 1],
        ]]]);

        $actions = $result['data']['items'][0]['actions'];
        $this->assertSame('https://admin.test/' . $path . '/edit/' . $idField . '=5', $actions['edit']['href']);
        $this->assertSame('Edit', (string)$actions['edit']['label']);
        $this->assertSame('https://admin.test/' . $path . '/delete/' . $idField . '=5', $actions['delete']['href']);
        $this->assertSame($title, (string)$actions['delete']['confirm']['title']);
        $this->assertArrayNotHasKey('actions', $result['data']['items'][1]);
    }

    public static function classProvider(): array
    {
        return [[ItemActions::class], [CategoryActions::class]];
    }

    #[DataProvider('classProvider')]
    public function testDataSourceWithoutItemsIsUnchanged(string $class): void
    {
        $column = new $class(
            $this->createStub(ContextInterface::class),
            $this->createStub(UiComponentFactory::class),
            $this->createStub(UrlInterface::class)
        );

        $this->assertSame(['data' => ['totalRecords' => 0]], $column->prepareDataSource(['data' => ['totalRecords' => 0]]));
    }
}
