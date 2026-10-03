<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Ui\Component\Listing\Column;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;

trait RelationColumnTrait
{
    protected array $rowsByItem = [];
    protected array $itemWheres = [];

    protected function relationColumn(string $class, array $data = []): object
    {
        $select = $this->createStub(Select::class);
        foreach (['from', 'joinLeft', 'order', 'limit'] as $method) {
            $select->method($method)->willReturnSelf();
        }
        $select->method('where')->willReturnCallback(function ($cond, $value = null) use ($select) {
            if (str_contains($cond, 'item_id')) {
                $this->itemWheres[] = $value;
            }
            return $select;
        });
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('getTableName')->willReturnArgument(0);
        $connection->method('fetchAll')->willReturnCallback(
            fn () => $this->rowsByItem[end($this->itemWheres)] ?? []
        );
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        return new $class(
            $this->createStub(ContextInterface::class),
            $this->createStub(UiComponentFactory::class),
            $resource,
            [],
            $data
        );
    }
}
