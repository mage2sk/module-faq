<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Model\ResourceModel\Item;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Store\Model\Store;
use Panth\Faq\Model\ResourceModel\Item\Collection;
use Panth\Faq\Test\Unit\Model\ResourceModel\SelectRecorderTrait;
use PHPUnit\Framework\TestCase;

class CollectionTest extends TestCase
{
    use SelectRecorderTrait;

    private function collection(): Collection
    {
        return new class ($this->recordingSelect(), $this->quotingConnection()) extends Collection {
            public function __construct(private Select $fakeSelect, private AdapterInterface $fakeConnection)
            {
            }

            public function getSelect()
            {
                return $this->fakeSelect;
            }

            public function getConnection()
            {
                return $this->fakeConnection;
            }

            public function getTable($table)
            {
                return 'pfx_' . $table;
            }
        };
    }

    public function testRelationFiltersJoinTheirTables(): void
    {
        $collection = $this->collection();

        $this->assertSame($collection, $collection->addProductFilter(5));
        $collection->addCatalogCategoryFilter(6);
        $collection->addPageFilter(7);
        $collection->addCategoryFilter(8);

        $joins = $this->sqlCalls('join');
        $this->assertSame(['product_table' => 'pfx_panth_faq_item_product'], $joins[0][1]);
        $this->assertSame(['category_table' => 'pfx_panth_faq_item_catalog_category'], $joins[1][1]);
        $this->assertSame(['page_table' => 'pfx_panth_faq_item_page'], $joins[2][1]);
        $this->assertSame(['faq_category_table' => 'pfx_panth_faq_item_faq_category'], $joins[3][1]);
        $this->assertSame(
            [
                ['where', 'product_table.product_id = ?', 5],
                ['where', 'category_table.category_id = ?', 6],
                ['where', 'page_table.page_id = ?', 7],
                ['where', 'faq_category_table.faq_category_id = ?', 8],
            ],
            $this->sqlCalls('where')
        );
    }

    public function testFaqCategoryAssignmentFilterGroupsByItem(): void
    {
        $this->collection()->addFaqCategoryAssignmentFilter();

        $this->assertSame(['faq_cat' => 'pfx_panth_faq_item_faq_category'], $this->sqlCalls('join')[0][1]);
        $this->assertSame([['group', 'main_table.item_id']], $this->sqlCalls('group'));
    }

    public function testStoreFilterIncludesAdminAndJoinsStoreTableOnRender(): void
    {
        $collection = $this->collection();
        $collection->addStoreFilter(3);
        $collection->addStoreFilter(4);

        $filter = $collection->getFilter('store_table.store_id');
        $this->assertSame(['in' => [3, 0]], $filter['value']);
        $this->assertTrue($collection->getFlag('store_filter_added'));

        $this->renderFilters($collection);

        $this->assertSame(['store_table' => 'pfx_panth_faq_item_store'], $this->sqlCalls('join')[0][1]);
        $this->assertSame('main_table.item_id = store_table.item_id', $this->sqlCalls('join')[0][2]);
        $this->assertSame(['panth_faq_item_value' => 'pfx_panth_faq_item_value'], $this->sqlCalls('joinLeft')[0][1]);
        $this->assertStringEndsWith('panth_faq_item_value.store_id = 4', $this->sqlCalls('joinLeft')[0][2]);
    }

    public function testStoreObjectWithoutAdminScope(): void
    {
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(2);
        $collection = $this->collection();

        $collection->addStoreFilter($store, false);
        $this->renderFilters($collection);

        $this->assertSame(['in' => [2]], $collection->getFilter('store_table.store_id')['value']);
        $columns = $this->sqlCalls('columns');
        $this->assertCount(8, $columns);
        $this->assertSame(
            'COALESCE(panth_faq_item_value.question, main_table.question)',
            (string)$columns[0][1]['question']
        );
    }

    public function testAdminStoreDoesNotJoinScopedValues(): void
    {
        $collection = $this->collection();
        $collection->addStoreFilter(0);
        $collection->addStoreScope(0);
        $this->renderFilters($collection);

        $this->assertSame([], $this->sqlCalls('joinLeft'));
        $this->assertCount(1, $this->sqlCalls('join'));
    }

    public function testActiveFilterUsesMainTableWithoutScope(): void
    {
        $collection = $this->collection();
        $collection->addActiveFilter();
        $this->renderFilters($collection);
        $this->renderFilters($collection);

        $this->assertSame([['where', 'main_table.is_active = ?', 1]], $this->sqlCalls('where'));
        $this->assertSame([], $this->sqlCalls('join'));
    }

    public function testActiveAndSearchFiltersUseScopedValues(): void
    {
        $collection = $this->collection();
        $collection->addStoreScope(2)->addActiveFilter()->addSearchFilter('50%_off');
        $this->renderFilters($collection);
        $this->renderFilters($collection);

        $this->assertCount(1, $this->sqlCalls('joinLeft'));
        $this->assertSame(
            [
                ['where', 'COALESCE(panth_faq_item_value.is_active, main_table.is_active) = ?', 1],
                [
                    'where',
                    "COALESCE(panth_faq_item_value.question, main_table.question) LIKE '%50\\%\\_off%'"
                    . " OR COALESCE(panth_faq_item_value.answer, main_table.answer) LIKE '%50\\%\\_off%'",
                ],
            ],
            $this->sqlCalls('where')
        );
    }

    public function testSearchWithoutScopeUsesMainColumns(): void
    {
        $collection = $this->collection();
        $collection->addSearchFilter('ship');
        $this->renderFilters($collection);

        $this->assertSame(
            [['where', "main_table.question LIKE '%ship%' OR main_table.answer LIKE '%ship%'"]],
            $this->sqlCalls('where')
        );
    }

    public function testEmptySearchIsIgnored(): void
    {
        $collection = $this->collection();
        $collection->addSearchFilter('');
        $this->renderFilters($collection);

        $this->assertSame([], $this->sql);
    }
}
