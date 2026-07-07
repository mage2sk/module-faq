<?php
declare(strict_types=1);

namespace Panth\Faq\Model\ResourceModel\Item;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Panth\Faq\Model\Item as ItemModel;
use Panth\Faq\Model\ResourceModel\Item as ItemResourceModel;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'item_id';

    protected $storeScopeId = null;

    protected function _construct()
    {
        $this->_init(ItemModel::class, ItemResourceModel::class);
    }

    public function addStoreFilter($storeId, $withAdmin = true)
    {
        if (!$this->getFlag('store_filter_added')) {
            $this->performAddStoreFilter($storeId, $withAdmin);
            $this->setFlag('store_filter_added', true);
        }

        if (is_scalar($storeId) && (int)$storeId > 0) {
            $this->addStoreScope((int)$storeId);
        } elseif ($storeId instanceof \Magento\Store\Model\Store && (int)$storeId->getId() > 0) {
            $this->addStoreScope((int)$storeId->getId());
        }
        return $this;
    }

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

        $this->addFilter('store_table.store_id', ['in' => $storeId], 'public');
    }

    public function addStoreScope(int $storeId): self
    {
        if ($storeId > 0) {
            $this->storeScopeId = $storeId;
        }
        return $this;
    }

    protected function _renderFiltersBefore()
    {
        $this->joinStoreRelationTable('panth_faq_item_store', 'item_id');
        $this->applyStoreScopeJoin();
        $this->applyActiveFilter();
        parent::_renderFiltersBefore();
    }

    protected function applyStoreScopeJoin(): void
    {
        if ($this->storeScopeId === null || $this->storeScopeId <= 0) {
            return;
        }
        if ($this->getFlag('panth_faq_item_value_joined')) {
            return;
        }
        $this->setFlag('panth_faq_item_value_joined', true);

        $select = $this->getSelect();
        $valueTable = $this->getTable(ItemResourceModel::ITEM_VALUE_TABLE);
        $select->joinLeft(
            ['panth_faq_item_value' => $valueTable],
            'main_table.item_id = panth_faq_item_value.item_id'
                . ' AND panth_faq_item_value.store_id = ' . (int)$this->storeScopeId,
            []
        );

        foreach (ItemResourceModel::SCOPED_FIELDS as $field) {
            $select->columns([
                $field => new \Zend_Db_Expr(
                    'COALESCE(panth_faq_item_value.' . $field
                    . ', main_table.' . $field . ')'
                ),
            ]);
        }
    }

    protected function joinStoreRelationTable($tableName, $columnName)
    {
        if ($this->getFilter('store_id') || $this->getFilter('store_table.store_id')) {
            $this->getSelect()->join(
                ['store_table' => $this->getTable($tableName)],
                'main_table.' . $columnName . ' = store_table.' . $columnName,
                []
            )->group(
                'main_table.' . $columnName
            );
        }
    }

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

    public function addActiveFilter()
    {
        $this->setFlag('panth_faq_active_filter_pending', true);
        return $this;
    }

    protected function applyActiveFilter(): void
    {
        if (!$this->getFlag('panth_faq_active_filter_pending')) {
            return;
        }
        if ($this->getFlag('panth_faq_active_filter_applied')) {
            return;
        }
        $this->setFlag('panth_faq_active_filter_applied', true);

        if ($this->storeScopeId !== null && $this->storeScopeId > 0) {
            $this->getSelect()->where(
                'COALESCE(panth_faq_item_value.is_active, main_table.is_active) = ?',
                1
            );
        } else {
            $this->getSelect()->where('main_table.is_active = ?', 1);
        }
    }
}
