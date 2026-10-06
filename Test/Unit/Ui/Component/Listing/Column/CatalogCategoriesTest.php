<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Ui\Component\Listing\Column;

use Panth\Faq\Ui\Component\Listing\Column\CatalogCategories;
use PHPUnit\Framework\TestCase;

class CatalogCategoriesTest extends TestCase
{
    use RelationColumnTrait;

    public function testRendersCategoryNamesIntoNamedColumn(): void
    {
        $this->rowsByItem = [
            3 => [
                ['category_id' => '20', 'value' => 'Men & Women Clothing Sale'],
                ['category_id' => '21', 'value' => null],
            ],
        ];

        $result = $this->relationColumn(CatalogCategories::class, ['name' => 'catalog_categories'])
            ->prepareDataSource(['data' => ['items' => [['item_id' => 3], ['item_id' => 4]]]]);

        $html = $result['data']['items'][0]['catalog_categories'];
        $this->assertStringContainsString('title="Catalog Category: Men &amp; Women Clothing Sale (ID: 20)"', $html);
        $this->assertStringContainsString('>Men &amp; Women Clothing...</span>', $html);
        $this->assertStringContainsString('>Category #21</span>', $html);
        $this->assertStringContainsString('No catalog categories', $result['data']['items'][1]['catalog_categories']);
        $this->assertSame([3, 4], $this->itemWheres);
    }
}
