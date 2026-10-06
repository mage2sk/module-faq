<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Model\Config\Source;

use Panth\Faq\Model\Config\Source\ProductPosition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProductPositionTest extends TestCase
{
    public function testOptions(): void
    {
        $options = (new ProductPosition())->toOptionArray();

        $this->assertSame(['tab', 'below_tabs', 'product_info'], array_column($options, 'value'));
        $this->assertSame(
            [
                'FAQ tab in the product tabs (recommended)',
                'Full-width section below the product tabs',
                'Product info column, under Add to Cart (old style)',
            ],
            array_map(static fn ($o) => (string)$o['label'], $options)
        );
    }

    public static function normalizeCases(): array
    {
        return [
            'tab' => ['tab', 'tab'],
            'below tabs' => ['below_tabs', 'below_tabs'],
            'product info' => ['product_info', 'product_info'],
            'empty' => [null, 'tab'],
            'legacy after description' => ['after_description', 'tab'],
            'legacy before related' => ['before_related', 'tab'],
            'unknown' => ['sidebar', 'tab'],
        ];
    }

    #[DataProvider('normalizeCases')]
    public function testNormalize($value, string $expected): void
    {
        $this->assertSame($expected, ProductPosition::normalize($value));
    }
}
