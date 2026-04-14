<?php
/**
 * FAQ Item Grid Collection
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Model\ResourceModel\Item\Grid;

use Magento\Framework\Data\Collection\Db\FetchStrategyInterface as FetchStrategy;
use Magento\Framework\Data\Collection\EntityFactoryInterface as EntityFactory;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Psr\Log\LoggerInterface as Logger;

/**
 * FAQ Item Grid Collection
 * Extends SearchResult to add category_id field for grid display
 */
class Collection extends SearchResult
{
    /**
     * Initialize collection
     *
     * @param EntityFactory $entityFactory
     * @param Logger $logger
     * @param FetchStrategy $fetchStrategy
     * @param EventManager $eventManager
     * @param string $mainTable
     * @param string $resourceModel
     */
    public function __construct(
        EntityFactory $entityFactory,
        Logger $logger,
        FetchStrategy $fetchStrategy,
        EventManager $eventManager,
        $mainTable = 'panth_faq_item',
        $resourceModel = \Panth\Faq\Model\ResourceModel\Item::class
    ) {
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $mainTable,
            $resourceModel
        );
    }

    /**
     * Initialize select with category IDs from junction table
     *
     * @return $this
     */
    protected function _renderFiltersBefore()
    {
        // Add category IDs as comma-separated list using GROUP_CONCAT
        $junctionTable = $this->getTable('panth_faq_item_faq_category');

        if (!$this->getFlag('category_join_added')) {
            $this->getSelect()->joinLeft(
                ['faq_cat' => $junctionTable],
                'main_table.item_id = faq_cat.item_id',
                ['category_id' => new \Magento\Framework\DB\Sql\Expression('GROUP_CONCAT(faq_cat.faq_category_id)')]
            );
            $this->getSelect()->group('main_table.item_id');
            $this->setFlag('category_join_added', true);
        }

        parent::_renderFiltersBefore();
    }
}
