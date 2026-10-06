<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Ui\Component\Listing;

use Magento\Framework\Api\Filter;
use Magento\Framework\Data\Collection;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Panth\Faq\Ui\Component\Listing\LikeFulltextFilter;
use PHPUnit\Framework\TestCase;

class LikeFulltextFilterTest extends TestCase
{
    private ?string $where = null;

    private function collection(): AbstractDb
    {
        $select = $this->createStub(Select::class);
        $select->method('where')->willReturnCallback(function ($cond) use ($select) {
            $this->where = $cond;
            return $select;
        });
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('quoteInto')->willReturnCallback(
            fn ($text, $value) => str_replace('?', "'" . $value . "'", $text)
        );
        $collection = $this->createStub(AbstractDb::class);
        $collection->method('getConnection')->willReturn($connection);
        $collection->method('getSelect')->willReturn($select);

        return $collection;
    }

    public function testBuildsEscapedLikeAcrossColumns(): void
    {
        (new LikeFulltextFilter())->apply($this->collection(), new Filter(['value' => ' 50%_off ']));

        $this->assertSame(
            "main_table.question LIKE '%50\\%\\_off%' OR main_table.answer LIKE '%50\\%\\_off%'"
            . " OR main_table.url_key LIKE '%50\\%\\_off%'",
            $this->where
        );
    }

    public function testLongValuesAreCappedAt200Characters(): void
    {
        (new LikeFulltextFilter())->apply($this->collection(), new Filter(['value' => str_repeat('a', 300)]));

        $this->assertStringContainsString("'%" . str_repeat('a', 200) . "%'", (string)$this->where);
        $this->assertStringNotContainsString(str_repeat('a', 201), (string)$this->where);
    }

    public function testEmptyOrNonScalarValuesAreIgnored(): void
    {
        $filter = new LikeFulltextFilter();
        $filter->apply($this->collection(), new Filter(['value' => '   ']));
        $filter->apply($this->collection(), new Filter(['value' => ['a']]));

        $this->assertNull($this->where);
    }

    public function testNonDbCollectionIsIgnored(): void
    {
        $filter = $this->createMock(Filter::class);
        $filter->expects($this->never())->method('getValue');

        (new LikeFulltextFilter())->apply($this->createStub(Collection::class), $filter);
    }

    public function testConfiguredColumnsReplaceTheItemDefaults(): void
    {
        $filter = new LikeFulltextFilter(['name' => 'main_table.name', 'url_key' => 'main_table.url_key']);

        $filter->apply($this->collection(), new Filter(['value' => 'returns']));

        $this->assertSame("main_table.name LIKE '%returns%' OR main_table.url_key LIKE '%returns%'", $this->where);
    }

    public function testCategoryGridIsWiredToTheLikeFilter(): void
    {
        $listing = (string)file_get_contents(
            dirname(__DIR__, 5) . '/view/adminhtml/ui_component/faq_category_listing.xml'
        );
        $di = (string)file_get_contents(dirname(__DIR__, 5) . '/etc/di.xml');

        $this->assertStringContainsString('<dataProvider class="Panth\\Faq\\Ui\\CategoryGridDataProvider"', $listing);
        $this->assertStringContainsString('<item name="name" xsi:type="string">main_table.name</item>', $di);
        $this->assertStringContainsString(
            '<item name="fulltext" xsi:type="object">Panth\\Faq\\Ui\\CategoryLikeFulltextFilter</item>',
            $di
        );
    }
}
