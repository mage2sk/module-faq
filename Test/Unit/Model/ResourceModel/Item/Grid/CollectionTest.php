<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Model\ResourceModel\Item\Grid;

use Magento\Framework\DB\Select;
use Panth\Faq\Model\ResourceModel\Item\Grid\Collection;
use Panth\Faq\Test\Unit\Model\ResourceModel\SelectRecorderTrait;
use PHPUnit\Framework\TestCase;

class CollectionTest extends TestCase
{
    use SelectRecorderTrait;

    public function testCategoryIdsAreAggregatedOnce(): void
    {
        $collection = new class ($this->recordingSelect()) extends Collection {
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

        $this->renderFilters($collection);
        $this->renderFilters($collection);

        $joins = $this->sqlCalls('joinLeft');
        $this->assertCount(1, $joins);
        $this->assertSame(['faq_cat' => 'pfx_panth_faq_item_faq_category'], $joins[0][1]);
        $this->assertSame('main_table.item_id = faq_cat.item_id', $joins[0][2]);
        $this->assertSame('GROUP_CONCAT(faq_cat.faq_category_id)', (string)$joins[0][3]['category_id']);
        $this->assertSame([['group', 'main_table.item_id']], $this->sqlCalls('group'));
        $this->assertTrue($collection->getFlag('category_join_added'));
    }
}
