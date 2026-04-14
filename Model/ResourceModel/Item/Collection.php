<?php
/**
 * FAQ Item Collection
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Model\ResourceModel\Item;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Panth\Faq\Model\Item as ItemModel;
use Panth\Faq\Model\ResourceModel\Item as ItemResourceModel;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'item_id';

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(ItemModel::class, ItemResourceModel::class);
    }

    /**
     * Add store filter
     *
     * @param int|array $storeId
     * @param bool $withAdmin
     * @return $this
     */
    public function addStoreFilter($storeId, $withAdmin = true)
    {
        if (!$this->getFlag('store_filter_added')) {
            $this->performAddStoreFilter($storeId, $withAdmin);
            $this->setFlag('store_filter_added', true);
        }
        return $this;
    }

    /**
     * Perform add store filter
     *
     * @param int|array $storeId
     * @param bool $withAdmin
     * @return void
     */
    protected function performAddStoreFilter($storeId, $withAdmin = true)
    {
        if ($storeId instanceof \Magento\Store\Model\Store) {
            $storeId = [$storeId->getId()];
        }

        if (!is_array($storeId)) {
            $storeId = [$storeId];
        }

        if ($withAdmin) {
            $storeId[] = 0;
        }

        $this->addFilter('store_id', ['in' => $storeId], 'public');
    }

    /**
     * Join store relation table
     *
     * @return void
     */
    protected function _renderFiltersBefore()
    {
        $this->joinStoreRelationTable('panth_faq_item_store', 'item_id');
        parent::_renderFiltersBefore();
    }

    /**
     * Join store relation table
     *
     * @param string $tableName
     * @param string $columnName
     * @return void
     */
    protected function joinStoreRelationTable($tableName, $columnName)
    {
        if ($this->getFilter('store_id')) {
            $this->getSelect()->join(
                ['store_table' => $this->getTable($tableName)],
                'main_table.' . $columnName . ' = store_table.' . $columnName,
                []
            )->group(
                'main_table.' . $columnName
            );
        }
    }

    /**
     * Add product filter
     *
     * @param int $productId
     * @return $this
     */
    public function addProductFilter($productId)
    {
        $this->getSelect()->join(
            ['product_table' => $this->getTable('panth_faq_item_product')],
            'main_table.item_id = product_table.item_id',
            []
        )->where(
            'product_table.product_id = ?',
            $productId
        );

        return $this;
    }

    /**
     * Add catalog category filter
     *
     * @param int $categoryId
     * @return $this
     */
    public function addCatalogCategoryFilter($categoryId)
    {
        $this->getSelect()->join(
            ['category_table' => $this->getTable('panth_faq_item_catalog_category')],
            'main_table.item_id = category_table.item_id',
            []
        )->where(
            'category_table.category_id = ?',
            $categoryId
        );

        return $this;
    }

    /**
     * Add page filter
     *
     * @param int $pageId
     * @return $this
     */
    public function addPageFilter($pageId)
    {
        $this->getSelect()->join(
            ['page_table' => $this->getTable('panth_faq_item_page')],
            'main_table.item_id = page_table.item_id',
            []
        )->where(
            'page_table.page_id = ?',
            $pageId
        );

        return $this;
    }

    /**
     * Add FAQ category filter
     *
     * @param int $categoryId
     * @return $this
     */
    public function addCategoryFilter($categoryId)
    {
        $this->getSelect()->join(
            ['faq_category_table' => $this->getTable('panth_faq_item_faq_category')],
            'main_table.item_id = faq_category_table.item_id',
            []
        )->where(
            'faq_category_table.faq_category_id = ?',
            $categoryId
        );

        return $this;
    }

    /**
     * Add active filter
     *
     * @return $this
     */
    public function addActiveFilter()
    {
        $this->addFieldToFilter('is_active', 1);
        return $this;
    }
}
