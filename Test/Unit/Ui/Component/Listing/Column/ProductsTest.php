<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Ui\Component\Listing\Column;

use Panth\Faq\Ui\Component\Listing\Column\Products;
use PHPUnit\Framework\TestCase;

class ProductsTest extends TestCase
{
    use RelationColumnTrait;

    public function testRendersProductLabelsWithFallbacks(): void
    {
        $this->rowsByItem = [
            7 => [
                ['product_id' => 10, 'name' => 'Shirt "Blue"', 'sku' => 'SH-1'],
                ['product_id' => 11, 'name' => null, 'sku' => 'SKU-11'],
                ['product_id' => 12, 'name' => '', 'sku' => ''],
            ],
        ];

        $result = $this->relationColumn(Products::class)->prepareDataSource(['data' => ['items' => [
            ['item_id' => 7],
            ['item_id' => 8],
        ]]]);
        $html = $result['data']['items'][0]['products'];

        $this->assertStringContainsString('title="ID: 10 - Shirt &quot;Blue&quot;"', $html);
        $this->assertStringContainsString('>SKU-11</span>', $html);
        $this->assertStringContainsString('>Product</span>', $html);
        $this->assertStringContainsString('None', $result['data']['items'][1]['products']);
    }

    public function testFiveProductsAddEllipsis(): void
    {
        $this->rowsByItem[7] = array_map(
            static fn ($i) => ['product_id' => $i, 'name' => 'Product number ' . $i . ' with long name', 'sku' => 's'],
            range(1, 5)
        );

        $result = $this->relationColumn(Products::class)->prepareDataSource(['data' => ['items' => [['item_id' => 7]]]]);
        $html = $result['data']['items'][0]['products'];

        $this->assertStringContainsString('>Product number 1 wit...</span>', $html);
        $this->assertStringEndsWith('font-size: 12px;">...</span>', $html);
    }
}
