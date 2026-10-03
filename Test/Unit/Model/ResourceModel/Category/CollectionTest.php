<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Model\ResourceModel\Category;

use Magento\Framework\DB\Select;
use Magento\Store\Model\Store;
use Panth\Faq\Model\ResourceModel\Category\Collection;
use Panth\Faq\Test\Unit\Model\ResourceModel\SelectRecorderTrait;
use PHPUnit\Framework\TestCase;

class CollectionTest extends TestCase
{
    use SelectRecorderTrait;

    private function collection(): Collection
    {
        return new class ($this->recordingSelect()) extends Collection {
            public function __construct(private Select $fakeSelect)
            {
            }

            public function getSelect()
            {
                return $this->fakeSelect;
            }

            public function getTable($table)
            {
                return 'pfx_' . $table;
            }
        };
    }

    public function testStoreFilterJoinsStoreAndValueTables(): void
    {
        $collection = $this->collection();
        $this->assertSame($collection, $collection->addStoreFilter('3'));
        $collection->addActiveFilter();
        $this->renderFilters($collection);
        $this->renderFilters($collection);

        $this->assertSame(['in' => ['3', 0]], $collection->getFilter('store_table.store_id')['value']);
        $this->assertSame(['store_table' => 'pfx_panth_faq_category_store'], $this->sqlCalls('join')[0][1]);
        $this->assertSame([['group', 'main_table.category_id']], array_slice($this->sqlCalls('group'), 0, 1));
        $this->assertCount(1, $this->sqlCalls('joinLeft'));
        $this->assertStringEndsWith('panth_faq_category_value.store_id = 3', $this->sqlCalls('joinLeft')[0][2]);
        $this->assertCount(8, $this->sqlCalls('columns'));
        $this->assertSame(
            [['where', 'COALESCE(panth_faq_category_value.is_active, main_table.is_active) = ?', 1]],
            $this->sqlCalls('where')
        );
    }

    public function testStoreObjectFilter(): void
    {
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(5);
        $collection = $this->collection();

        $collection->addStoreFilter($store, false);

        $this->assertSame(['in' => [5]], $collection->getFilter('store_table.store_id')['value']);
    }

    public function testActiveFilterWithoutScope(): void
    {
        $collection = $this->collection();
        $collection->addStoreScope(0)->addActiveFilter();
        $this->renderFilters($collection);

        $this->assertSame([['where', 'main_table.is_active = ?', 1]], $this->sql);
    }

    public function testNothingRenderedWithoutFilters(): void
    {
        $this->renderFilters($this->collection());

        $this->assertSame([], $this->sql);
    }
}
