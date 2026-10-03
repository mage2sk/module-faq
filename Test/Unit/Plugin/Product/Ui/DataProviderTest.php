<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Plugin\Product\Ui;

use Magento\Catalog\Ui\DataProvider\Product\Form\ProductDataProvider;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Panth\Faq\Plugin\Product\Ui\DataProvider;
use PHPUnit\Framework\TestCase;

class DataProviderTest extends TestCase
{
    private array $wheres = [];

    private function plugin(array $faqIdsByProduct): DataProvider
    {
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnCallback(function ($cond, $value) use ($select) {
            $this->wheres[] = [$cond, $value];
            return $select;
        });
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('getTableName')->willReturnArgument(0);
        $connection->method('fetchCol')->willReturnCallback(function () use ($faqIdsByProduct) {
            $last = end($this->wheres);
            return $faqIdsByProduct[$last[1]] ?? [];
        });
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);

        return new DataProvider($resource);
    }

    public function testNonArrayResultIsReturnedUntouched(): void
    {
        $subject = $this->createStub(ProductDataProvider::class);

        $this->assertNull($this->plugin([])->afterGetData($subject, null));
        $this->assertSame('x', $this->plugin([])->afterGetData($subject, 'x'));
    }

    public function testAddsAssignedFaqIdsToEachProduct(): void
    {
        $result = [
            10 => ['product' => ['sku' => 'A']],
            11 => ['product' => ['sku' => 'B']],
            'config' => ['something' => true],
        ];

        $data = $this->plugin([10 => ['3', '4']])->afterGetData(
            $this->createStub(ProductDataProvider::class),
            $result
        );

        $this->assertSame(['sku' => 'A', 'faq_items' => ['3', '4']], $data[10]['product']);
        $this->assertSame(['sku' => 'B', 'faq_items' => []], $data[11]['product']);
        $this->assertSame(['something' => true], $data['config']);
        $this->assertSame([['product_id = ?', 10], ['product_id = ?', 11]], $this->wheres);
    }
}
