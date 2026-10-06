<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Model\Config\Source;

use Panth\Faq\Model\Config\Source\DefaultState;
use PHPUnit\Framework\TestCase;

class DefaultStateTest extends TestCase
{
    public function testOptions(): void
    {
        $options = (new DefaultState())->toOptionArray();

        $this->assertSame([0, 1, 2], array_column($options, 'value'));
        $this->assertSame(
            ['All Collapsed', 'All Expanded', 'First Item Expanded'],
            array_map(static fn ($o) => (string)$o['label'], $options)
        );
    }
}
