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
     * Store ID whose overrides should be merged into each row.
     * Set via {@see addStoreScope()}; 0/null = no merge (raw main rows).
     *
     * @var int|null
     */
    protected $storeScopeId = null;

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
        // Auto-apply scoped value merge when a concrete store_id is given.
        // Backward compat: callers passing 0, an array, or a Store admin
        // scope continue to get the un-merged main row values exactly
        // like pre-1.1.0.
        if (is_scalar($storeId) && (int)$storeId > 0) {
            $this->addStoreScope((int)$storeId);
        } elseif ($storeId instanceof \Magento\Store\Model\Store && (int)$storeId->getId() > 0) {
            $this->addStoreScope((int)$storeId->getId());
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

        // Qualify with store_table.* — once the value-overlay table is also
        // joined (panth_faq_item_value, with its own store_id column), an
        // unqualified `store_id` raises 1052 "ambiguous column".
        $this->addFilter('store_table.store_id', ['in' => $storeId], 'public');
    }

    /**
     * Merge per-store override values into every loaded item.
     *
     * Generates a LEFT JOIN against panth_faq_item_value for the given store
     * and replaces the SELECT clause for each scoped field with
     *   COALESCE(value.<field>, main.<field>) AS <field>
     * so storefront listings (and JSON-LD output) automatically pick up the
     * per-store version of question / answer / url_key / is_active / etc.
     *
     * @param int $storeId positive store_id; 0 disables the merge.
     * @return $this
     */
    public function addStoreScope(int $storeId): self
    {
        if ($storeId > 0) {
            $this->storeScopeId = $storeId;
        }
        return $this;
    }

    /**
     * Join store relation table + scoped value overlay.
     *
     * @return void
     */
    protected function _renderFiltersBefore()
    {
        $this->joinStoreRelationTable('panth_faq_item_store', 'item_id');
        $this->applyStoreScopeJoin();
        $this->applyActiveFilter();
        parent::_renderFiltersBefore();
    }

    /**
     * Apply the COALESCE(override, main) join. Idempotent — repeated calls
     * (e.g. via getSize() then load()) won't duplicate columns.
     *
     * @return void
     */
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
        // Replace each scoped field with its COALESCE expression. We use
        // resetColumnAliases via columns() with an alias matching the
        // field name; Zend\Db\Select honours the last alias for that key.
        foreach (ItemResourceModel::SCOPED_FIELDS as $field) {
            $select->columns([
                $field => new \Zend_Db_Expr(
                    'COALESCE(panth_faq_item_value.' . $field
                    . ', main_table.' . $field . ')'
                ),
            ]);
        }
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
     * Add active filter — store-scope aware.
     *
     * The naive `is_active = 1` clause becomes ambiguous after we LEFT JOIN
     * panth_faq_item_value (which also has is_active). Defer the filter
     * until _renderFiltersBefore so we can apply it AFTER the value table
     * is joined, using COALESCE(override.is_active, main.is_active) = 1.
     *
     * In default scope (no value join), it falls back to main_table.is_active.
     *
     * @return $this
     */
    public function addActiveFilter()
    {
        $this->setFlag('panth_faq_active_filter_pending', true);
        return $this;
    }

    /**
     * Apply the deferred active filter once joins are in place.
     *
     * @return void
     */
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
