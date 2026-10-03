<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Model\Config\Source;

use Panth\Faq\Model\Config\Source\CategoryPosition;
use PHPUnit\Framework\TestCase;

class CategoryPositionTest extends TestCase
{
    public function testOptions(): void
    {
        $options = (new CategoryPosition())->toOptionArray();

        $this->assertSame(['top', 'bottom', 'sidebar_top', 'sidebar_bottom'], array_column($options, 'value'));
        $this->assertSame(
            ['Top of Content', 'Bottom of Content', 'Top of Sidebar', 'Bottom of Sidebar'],
            array_map(static fn ($o) => (string)$o['label'], $options)
        );
    }
}
