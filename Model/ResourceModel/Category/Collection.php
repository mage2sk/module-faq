<?php
declare(strict_types=1);

namespace Panth\Faq\Model\ResourceModel\Category;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Panth\Faq\Model\Category as CategoryModel;
use Panth\Faq\Model\ResourceModel\Category as CategoryResourceModel;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'category_id';

    protected $storeScopeId = null;

    protected function _construct()
    {
        $this->_init(CategoryModel::class, CategoryResourceModel::class);
    }

    public function addStoreScope(int $storeId): self
    {
        if ($storeId > 0) {
            $this->storeScopeId = $storeId;
        }
        return $this;
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

    protected function _renderFiltersBefore()
    {
        $this->joinStoreRelationTable('panth_faq_category_store', 'category_id');
        $this->applyStoreScopeJoin();
        $this->applyActiveFilter();
        parent::_renderFiltersBefore();
    }

    protected function applyStoreScopeJoin(): void
    {
        if ($this->storeScopeId === null || $this->storeScopeId <= 0) {
            return;
        }
        if ($this->getFlag('panth_faq_category_value_joined')) {
            return;
        }
        $this->setFlag('panth_faq_category_value_joined', true);

        $select = $this->getSelect();
        $valueTable = $this->getTable(CategoryResourceModel::CATEGORY_VALUE_TABLE);
        $select->joinLeft(
            ['panth_faq_category_value' => $valueTable],
            'main_table.category_id = panth_faq_category_value.category_id'
                . ' AND panth_faq_category_value.store_id = ' . (int)$this->storeScopeId,
            []
        );
        foreach (CategoryResourceModel::SCOPED_FIELDS as $field) {
            $select->columns([
                $field => new \Zend_Db_Expr(
                    'COALESCE(panth_faq_category_value.' . $field
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

    public function addActiveFilter()
    {
        $this->setFlag('panth_faq_cat_active_filter_pending', true);
        return $this;
    }

    protected function applyActiveFilter(): void
    {
        if (!$this->getFlag('panth_faq_cat_active_filter_pending')) {
            return;
        }
        if ($this->getFlag('panth_faq_cat_active_filter_applied')) {
            return;
        }
        $this->setFlag('panth_faq_cat_active_filter_applied', true);

        if ($this->storeScopeId !== null && $this->storeScopeId > 0) {
            $this->getSelect()->where(
                'COALESCE(panth_faq_category_value.is_active, main_table.is_active) = ?',
                1
            );
        } else {
            $this->getSelect()->where('main_table.is_active = ?', 1);
        }
    }
}
