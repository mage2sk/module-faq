<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Model\Config\Source;

use Panth\Faq\Model\Config\Source\ProductPosition;
use PHPUnit\Framework\TestCase;

class ProductPositionTest extends TestCase
{
    public function testOptions(): void
    {
        $options = (new ProductPosition())->toOptionArray();

        $this->assertSame(
            ['tab', 'after_description', 'after_additional', 'before_related'],
            array_column($options, 'value')
        );
        $this->assertSame(
            ['As Tab', 'After Description', 'After Additional Information', 'Before Related Products'],
            array_map(static fn ($o) => (string)$o['label'], $options)
        );
    }
}
