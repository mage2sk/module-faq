<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Ui\Component\Listing\Column;

use Panth\Faq\Ui\Component\Listing\Column\Pages;
use PHPUnit\Framework\TestCase;

class PagesTest extends TestCase
{
    use RelationColumnTrait;

    public function testRendersPageLabelsWithFallbacks(): void
    {
        $this->rowsByItem = [
            1 => [
                ['page_id' => 2, 'title' => 'About <us>', 'identifier' => 'about'],
                ['page_id' => 3, 'title' => '', 'identifier' => 'shipping-policy'],
                ['page_id' => 4, 'title' => null, 'identifier' => ''],
                ['page_id' => 5, 'title' => 'A very long page title that is cut', 'identifier' => 'x'],
            ],
        ];

        $result = $this->relationColumn(Pages::class)->prepareDataSource(['data' => ['items' => [
            ['item_id' => 1],
            ['item_id' => 2],
        ]]]);
        $html = $result['data']['items'][0]['cms_pages'];

        $this->assertStringContainsString('title="ID: 2 - About &lt;us&gt;"', $html);
        $this->assertStringContainsString('>shipping-policy</span>', $html);
        $this->assertStringContainsString('>Page</span>', $html);
        $this->assertStringContainsString('>A very long page tit...</span>', $html);
        $this->assertStringNotContainsString('font-size: 12px;">...</span>', $html);
        $this->assertStringContainsString('None', $result['data']['items'][1]['cms_pages']);
        $this->assertSame([1, 2], $this->itemWheres);
    }

    public function testFiveOrMorePagesAddEllipsis(): void
    {
        $this->rowsByItem[1] = array_map(
            static fn ($i) => ['page_id' => $i, 'title' => 'P' . $i, 'identifier' => 'p' . $i],
            range(1, 5)
        );

        $result = $this->relationColumn(Pages::class)->prepareDataSource(['data' => ['items' => [['item_id' => 1]]]]);

        $this->assertStringEndsWith('<span style="color: #666; font-size: 12px;">...</span>', $result['data']['items'][0]['cms_pages']);
    }
}
