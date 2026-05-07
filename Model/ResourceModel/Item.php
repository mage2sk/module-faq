<?php
/**
 * FAQ Item Resource Model
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Model\ResourceModel;

use Magento\Framework\App\Area;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Store\Model\StoreManagerInterface;

class Item extends AbstractDb
{
    /**
     * Store table name
     */
    const ITEM_STORE_TABLE = 'panth_faq_item_store';
    const ITEM_PRODUCT_TABLE = 'panth_faq_item_product';
    const ITEM_CATALOG_CATEGORY_TABLE = 'panth_faq_item_catalog_category';
    const ITEM_PAGE_TABLE = 'panth_faq_item_page';
    const ITEM_FAQ_CATEGORY_TABLE = 'panth_faq_item_faq_category';
    const ITEM_VALUE_TABLE = 'panth_faq_item_value';

    /**
     * Columns whose values can be overridden per store view.
     * Order matters only for whitelist clarity; the resource model
     * iterates this list when reading/writing override rows.
     *
     * @var string[]
     */
    public const SCOPED_FIELDS = [
        'question',
        'answer',
        'url_key',
        'is_active',
        'show_on_main',
        'meta_title',
        'meta_description',
        'meta_keywords',
    ];

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var EventManager
     */
    protected $eventManager;

    /**
     * @var AppState
     */
    protected $appState;

    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        EventManager $eventManager,
        AppState $appState,
        $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
        $this->storeManager = $storeManager;
        $this->eventManager = $eventManager;
        $this->appState = $appState;
    }

    /**
     * Resolve the active store-scope id to use for an in-flight load.
     * Honours an explicit `store_scope_id` on the model first; otherwise
     * falls back to the current frontend store. In adminhtml / cron / cli
     * contexts the auto-detect is skipped so the main-row defaults stay
     * canonical for admin grids and CLI tasks.
     */
    protected function resolveStoreScopeId(AbstractModel $object): int
    {
        $explicit = (int)$object->getData('store_scope_id');
        if ($explicit > 0) {
            return $explicit;
        }
        try {
            $area = $this->appState->getAreaCode();
        } catch (\Throwable) {
            return 0;
        }
        if ($area !== Area::AREA_FRONTEND) {
            return 0;
        }
        try {
            $current = $this->storeManager->getStore();
            // store_id 0 == admin scope; we only auto-merge for real
            // storefront views (store_id > 0).
            if ($current && (int)$current->getId() > 0) {
                return (int)$current->getId();
            }
        } catch (\Throwable) {
        }
        return 0;
    }

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('panth_faq_item', 'item_id');
    }

    /**
     * Get connection
     *
     * @return \Magento\Framework\DB\Adapter\AdapterInterface
     */
    public function getConnection()
    {
        return parent::getConnection();
    }

    /**
     * Magento\Framework\Model\ResourceModel\Db\AbstractDb::load() does
     * `$object->setData($freshDataArray)` which replaces every model
     * property. Any caller-supplied `store_scope_id` is therefore wiped
     * before _afterLoad runs and our merge has nothing to look at.
     *
     * Preserve the scope across the parent load.
     */
    public function load(AbstractModel $object, $value, $field = null)
    {
        $scope = $this->resolveStoreScopeId($object);
        $result = parent::load($object, $value, $field);
        if ($scope > 0) {
            $object->setData('store_scope_id', $scope);
            $this->mergeStoreOverrides($object);
        }
        return $result;
    }

    /**
     * Save store relation
     *
     * @param AbstractModel $object
     * @return $this
     */
    /**
     * When saving in a store-view scope (store_scope_id > 0), we MUST NOT
     * write the scoped values to the main table — that would pollute the
     * default value across every store. Stash the scoped values, swap in
     * the persisted defaults, let the parent UPDATE run, then restore the
     * scoped values for _afterSave to consume.
     */
    protected function _beforeSave(AbstractModel $object)
    {
        $storeScopeId = (int)$object->getData('store_scope_id');
        if ($storeScopeId > 0 && (int)$object->getId() > 0) {
            $defaults = $this->loadDefaultValues((int)$object->getId());
            $snapshot = [];
            foreach (self::SCOPED_FIELDS as $field) {
                $snapshot[$field] = $object->getData($field);
                if (array_key_exists($field, $defaults)) {
                    $object->setData($field, $defaults[$field]);
                }
            }
            $object->setData('_panth_scope_snapshot', $snapshot);
        }
        return parent::_beforeSave($object);
    }

    protected function _afterSave(AbstractModel $object)
    {
        $snapshot = $object->getData('_panth_scope_snapshot');
        if (is_array($snapshot)) {
            foreach ($snapshot as $field => $value) {
                $object->setData($field, $value);
            }
            $object->unsetData('_panth_scope_snapshot');
        }

        $this->saveStoreRelation($object);
        $this->saveStoreValues($object);
        $this->saveProductRelation($object);
        $this->saveCatalogCategoryRelation($object);
        $this->savePageRelation($object);
        $this->saveFaqCategoryRelation($object);

        // Dispatch event for URL rewrite generation
        $this->eventManager->dispatch('panth_faq_item_save_after', ['item' => $object]);

        return parent::_afterSave($object);
    }

    /**
     * Public proxy for {@see loadDefaultValues()} — used by the URL rewrite
     * observer, which fires post-save and otherwise can't tell the model's
     * potentially-scoped values apart from the persisted defaults.
     *
     * @return array<string, mixed>
     */
    public function loadDefaultValuesPublic(int $itemId): array
    {
        return $this->loadDefaultValues($itemId);
    }

    /**
     * Read the persisted "default" (main-table) values for scoped fields.
     * Used by _beforeSave to keep the main row clean during a store-scope save.
     *
     * @param int $itemId
     * @return array<string, mixed>
     */
    protected function loadDefaultValues(int $itemId): array
    {
        if ($itemId <= 0) {
            return [];
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), self::SCOPED_FIELDS)
            ->where('item_id = ?', $itemId)
            ->limit(1);
        return (array)($connection->fetchRow($select) ?: []);
    }

    /**
     * Persist per-store overrides.
     *
     * Save semantics (CMS-Page-style):
     *   - When the model has store_scope_id == 0 / null, no per-store row is
     *     written or modified — the main row already received the values.
     *   - When store_scope_id is a positive store_id, an override row is
     *     upserted for that (item_id, store_id). Each scoped field is set to
     *     the model's current value unless the model's `use_default` array
     *     contains that field name, in which case the field is stored as NULL
     *     (= "inherit default"). If every scoped field would be NULL, the
     *     override row is deleted instead so the storefront just falls
     *     through to the main row.
     *
     * Backward compat: code that doesn't set store_scope_id behaves exactly
     * like pre-1.1.0 (only the main row is touched).
     *
     * @param AbstractModel $object
     * @return $this
     */
    protected function saveStoreValues(AbstractModel $object): self
    {
        $storeId = (int)$object->getData('store_scope_id');
        if ($storeId <= 0) {
            return $this;
        }
        $itemId = (int)$object->getId();
        if ($itemId <= 0) {
            return $this;
        }

        $useDefault = (array)$object->getData('use_default');
        $row = ['item_id' => $itemId, 'store_id' => $storeId];
        $allNull = true;
        foreach (self::SCOPED_FIELDS as $field) {
            if (in_array($field, $useDefault, true)) {
                $row[$field] = null;
            } else {
                $value = $object->getData($field);
                $row[$field] = ($value === '' || $value === null) ? null : $value;
                if ($row[$field] !== null) {
                    $allNull = false;
                }
            }
        }

        $connection = $this->getConnection();
        $table = $this->getTable(self::ITEM_VALUE_TABLE);

        if ($allNull) {
            $connection->delete($table, [
                'item_id = ?' => $itemId,
                'store_id = ?' => $storeId,
            ]);
            return $this;
        }

        $connection->insertOnDuplicate(
            $table,
            $row,
            self::SCOPED_FIELDS
        );
        return $this;
    }

    /**
     * Read the override row for (itemId, storeId) and return field => value
     * (excluding NULL values, which mean "use default").
     *
     * @param int $itemId
     * @param int $storeId
     * @return array<string, mixed>
     */
    public function getStoreOverrides(int $itemId, int $storeId): array
    {
        if ($itemId <= 0 || $storeId <= 0) {
            return [];
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable(self::ITEM_VALUE_TABLE), self::SCOPED_FIELDS)
            ->where('item_id = ?', $itemId)
            ->where('store_id = ?', $storeId)
            ->limit(1);
        $row = $connection->fetchRow($select);
        if (!$row) {
            return [];
        }
        return array_filter($row, static fn ($v) => $v !== null);
    }

    /**
     * Read every scoped column verbatim (NULL kept) for a (item, store) pair.
     * Used by the admin DataProvider so it can populate the "use default"
     * checkboxes correctly: a NULL column in the override row means the
     * checkbox should be checked.
     *
     * @param int $itemId
     * @param int $storeId
     * @return array<string, mixed>
     */
    public function getStoreOverrideRow(int $itemId, int $storeId): array
    {
        if ($itemId <= 0 || $storeId <= 0) {
            return [];
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable(self::ITEM_VALUE_TABLE), self::SCOPED_FIELDS)
            ->where('item_id = ?', $itemId)
            ->where('store_id = ?', $storeId)
            ->limit(1);
        return (array)($connection->fetchRow($select) ?: []);
    }

    /**
     * Resolve item id for a given URL key, store-aware.
     *
     * Lookup order:
     *   1. override row whose url_key matches AND (is_active override = 1 OR
     *      override is NULL AND main is_active = 1)
     *   2. main row whose url_key matches AND no per-store override exists
     *      that points the same url_key elsewhere
     *
     * Falls back to a global lookup when storeId is 0.
     *
     * @return int|null
     */
    public function getItemIdByUrlKeyForStore(string $urlKey, int $storeId): ?int
    {
        if ($urlKey === '') {
            return null;
        }
        $connection = $this->getConnection();
        if ($storeId > 0) {
            $select = $connection->select()
                ->from(['v' => $this->getTable(self::ITEM_VALUE_TABLE)], ['item_id'])
                ->joinLeft(
                    ['m' => $this->getMainTable()],
                    'v.item_id = m.item_id',
                    []
                )
                ->where('v.store_id = ?', $storeId)
                ->where('v.url_key = ?', $urlKey)
                ->where('COALESCE(v.is_active, m.is_active) = ?', 1)
                ->limit(1);
            $id = $connection->fetchOne($select);
            if ($id) {
                return (int)$id;
            }
        }

        // Fallback: main row, but skip rows that have an override with a
        // different url_key for the current store (because the merchant
        // explicitly relabelled the URL on this store).
        $select = $connection->select()
            ->from(['m' => $this->getMainTable()], ['item_id'])
            ->joinLeft(
                ['v' => $this->getTable(self::ITEM_VALUE_TABLE)],
                'v.item_id = m.item_id AND v.store_id = ' . (int)$storeId,
                []
            )
            ->where('m.url_key = ?', $urlKey)
            ->where('COALESCE(v.is_active, m.is_active) = ?', 1)
            ->where('v.url_key IS NULL OR v.url_key = ?', $urlKey)
            ->limit(1);
        $id = $connection->fetchOne($select);
        return $id ? (int)$id : null;
    }

    /**
     * Save store relation
     *
     * @param AbstractModel $object
     * @return $this
     */
    protected function saveStoreRelation(AbstractModel $object)
    {
        $stores = $object->getStores();
        if ($stores !== null) {
            $connection = $this->getConnection();
            $table = $this->getTable(self::ITEM_STORE_TABLE);

            $connection->delete($table, ['item_id = ?' => $object->getId()]);

            $insertData = [];
            foreach ((array)$stores as $storeId) {
                $insertData[] = [
                    'item_id' => $object->getId(),
                    'store_id' => $storeId
                ];
            }

            if (!empty($insertData)) {
                $connection->insertMultiple($table, $insertData);
            }
        }

        return $this;
    }

    /**
     * Save product relation
     *
     * @param AbstractModel $object
     * @return $this
     */
    protected function saveProductRelation(AbstractModel $object)
    {
        $products = $object->getProducts();
        if ($products !== null) {
            $connection = $this->getConnection();
            $table = $this->getTable(self::ITEM_PRODUCT_TABLE);

            $connection->delete($table, ['item_id = ?' => $object->getId()]);

            $insertData = [];
            foreach ((array)$products as $productId) {
                $insertData[] = [
                    'item_id' => $object->getId(),
                    'product_id' => $productId,
                    'sort_order' => 0
                ];
            }

            if (!empty($insertData)) {
                $connection->insertMultiple($table, $insertData);
            }
        }

        return $this;
    }

    /**
     * Save catalog category relation
     *
     * @param AbstractModel $object
     * @return $this
     */
    protected function saveCatalogCategoryRelation(AbstractModel $object)
    {
        $categories = $object->getCatalogCategories();
        if ($categories !== null) {
            $connection = $this->getConnection();
            $table = $this->getTable(self::ITEM_CATALOG_CATEGORY_TABLE);

            $connection->delete($table, ['item_id = ?' => $object->getId()]);

            $insertData = [];
            foreach ((array)$categories as $categoryId) {
                $insertData[] = [
                    'item_id' => $object->getId(),
                    'category_id' => $categoryId,
                    'sort_order' => 0
                ];
            }

            if (!empty($insertData)) {
                $connection->insertMultiple($table, $insertData);
            }
        }

        return $this;
    }

    /**
     * Save page relation
     *
     * @param AbstractModel $object
     * @return $this
     */
    protected function savePageRelation(AbstractModel $object)
    {
        $pages = $object->getPages();
        if ($pages !== null) {
            $connection = $this->getConnection();
            $table = $this->getTable(self::ITEM_PAGE_TABLE);

            $connection->delete($table, ['item_id = ?' => $object->getId()]);

            $insertData = [];
            foreach ((array)$pages as $pageId) {
                $insertData[] = [
                    'item_id' => $object->getId(),
                    'page_id' => $pageId,
                    'sort_order' => 0
                ];
            }

            if (!empty($insertData)) {
                $connection->insertMultiple($table, $insertData);
            }
        }

        return $this;
    }

    /**
     * Save FAQ category relation
     *
     * @param AbstractModel $object
     * @return $this
     */
    protected function saveFaqCategoryRelation(AbstractModel $object)
    {
        $categoryIds = $object->getCategoryId();

        // Handle both single category_id and array of category_id
        if ($categoryIds !== null) {
            $connection = $this->getConnection();
            $table = $this->getTable(self::ITEM_FAQ_CATEGORY_TABLE);

            $connection->delete($table, ['item_id = ?' => $object->getId()]);

            $insertData = [];
            foreach ((array)$categoryIds as $categoryId) {
                if ($categoryId) {
                    $insertData[] = [
                        'item_id' => $object->getId(),
                        'faq_category_id' => $categoryId,
                        'sort_order' => 0
                    ];
                }
            }

            if (!empty($insertData)) {
                $connection->insertMultiple($table, $insertData);
            }
        }

        return $this;
    }

    /**
     * Load store relation
     *
     * @param AbstractModel $object
     * @return $this
     */
    protected function _afterLoad(AbstractModel $object)
    {
        $this->loadStoreRelation($object);
        $this->mergeStoreOverrides($object);
        $this->loadProductRelation($object);
        $this->loadCatalogCategoryRelation($object);
        $this->loadPageRelation($object);
        $this->loadFaqCategoryRelation($object);

        return parent::_afterLoad($object);
    }

    /**
     * If the model carries a positive `store_scope_id`, merge the override
     * row's non-null values onto the loaded model. The original ("default")
     * values are preserved under `<field>_default` keys so the admin form
     * can show them as the fallback when "Use Default" is ticked.
     *
     * No-ops on default scope (store_scope_id = 0 / null), preserving the
     * pre-1.1.0 behaviour for any caller that doesn't opt in.
     *
     * @param AbstractModel $object
     * @return $this
     */
    protected function mergeStoreOverrides(AbstractModel $object): self
    {
        $storeId = (int)$object->getData('store_scope_id');
        if ($storeId <= 0) {
            return $this;
        }
        $itemId = (int)$object->getId();
        if ($itemId <= 0) {
            return $this;
        }

        // Snapshot defaults before overlaying — admin DataProvider needs them.
        $defaults = [];
        foreach (self::SCOPED_FIELDS as $field) {
            $defaults[$field] = $object->getData($field);
        }
        $object->setData('store_default_values', $defaults);

        $overrideRow = $this->getStoreOverrideRow($itemId, $storeId);
        $useDefault = [];
        foreach (self::SCOPED_FIELDS as $field) {
            if (!array_key_exists($field, $overrideRow) || $overrideRow[$field] === null) {
                // Inherit default — already on the model from the main load.
                $useDefault[$field] = 1;
                continue;
            }
            $object->setData($field, $overrideRow[$field]);
        }
        $object->setData('use_default', $useDefault);

        return $this;
    }

    /**
     * Load store relation
     *
     * @param AbstractModel $object
     * @return $this
     */
    protected function loadStoreRelation(AbstractModel $object)
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable(self::ITEM_STORE_TABLE), 'store_id')
            ->where('item_id = ?', $object->getId());

        $stores = $connection->fetchCol($select);
        $object->setData('store_id', $stores);
        $object->setData('stores', $stores);

        return $this;
    }

    /**
     * Load product relation
     *
     * @param AbstractModel $object
     * @return $this
     */
    protected function loadProductRelation(AbstractModel $object)
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable(self::ITEM_PRODUCT_TABLE), 'product_id')
            ->where('item_id = ?', $object->getId());

        $products = $connection->fetchCol($select);
        $object->setData('products', is_array($products) ? $products : []);

        return $this;
    }

    /**
     * Load catalog category relation
     *
     * @param AbstractModel $object
     * @return $this
     */
    protected function loadCatalogCategoryRelation(AbstractModel $object)
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable(self::ITEM_CATALOG_CATEGORY_TABLE), 'category_id')
            ->where('item_id = ?', $object->getId());

        $categories = $connection->fetchCol($select);
        $object->setData('catalog_categories', is_array($categories) ? $categories : []);

        return $this;
    }

    /**
     * Load page relation
     *
     * @param AbstractModel $object
     * @return $this
     */
    protected function loadPageRelation(AbstractModel $object)
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable(self::ITEM_PAGE_TABLE), 'page_id')
            ->where('item_id = ?', $object->getId());

        $pages = $connection->fetchCol($select);
        $object->setData('pages', is_array($pages) ? $pages : []);

        return $this;
    }

    /**
     * Load FAQ category relation
     *
     * @param AbstractModel $object
     * @return $this
     */
    protected function loadFaqCategoryRelation(AbstractModel $object)
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable(self::ITEM_FAQ_CATEGORY_TABLE), 'faq_category_id')
            ->where('item_id = ?', $object->getId());

        $categoryIds = $connection->fetchCol($select);
        $categoryIds = is_array($categoryIds) ? $categoryIds : [];

        // Set category_id as array for multiselect
        $object->setData('category_id', $categoryIds);

        // Also keep the old single category_id for backwards compatibility
        if (!empty($categoryIds)) {
            $object->setData('faq_categories', $categoryIds);
        }

        return $this;
    }
}
