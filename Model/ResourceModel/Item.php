<?php
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
    const ITEM_STORE_TABLE = 'panth_faq_item_store';
    const ITEM_PRODUCT_TABLE = 'panth_faq_item_product';
    const ITEM_CATALOG_CATEGORY_TABLE = 'panth_faq_item_catalog_category';
    const ITEM_PAGE_TABLE = 'panth_faq_item_page';
    const ITEM_FAQ_CATEGORY_TABLE = 'panth_faq_item_faq_category';
    const ITEM_VALUE_TABLE = 'panth_faq_item_value';

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

    protected $storeManager;

    protected $eventManager;

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

            if ($current && (int)$current->getId() > 0) {
                return (int)$current->getId();
            }
        } catch (\Throwable) {
        }
        return 0;
    }

    protected function _construct()
    {
        $this->_init('panth_faq_item', 'item_id');
    }

    public function getConnection()
    {
        return parent::getConnection();
    }

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

        $this->eventManager->dispatch('panth_faq_item_save_after', ['item' => $object]);

        return parent::_afterSave($object);
    }

    public function loadDefaultValuesPublic(int $itemId): array
    {
        return $this->loadDefaultValues($itemId);
    }

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

    protected function saveFaqCategoryRelation(AbstractModel $object)
    {
        $categoryIds = $object->getCategoryId();

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

        $defaults = [];
        foreach (self::SCOPED_FIELDS as $field) {
            $defaults[$field] = $object->getData($field);
        }
        $object->setData('store_default_values', $defaults);

        $overrideRow = $this->getStoreOverrideRow($itemId, $storeId);
        $useDefault = [];
        foreach (self::SCOPED_FIELDS as $field) {
            if (!array_key_exists($field, $overrideRow) || $overrideRow[$field] === null) {
                $useDefault[$field] = 1;
                continue;
            }
            $object->setData($field, $overrideRow[$field]);
        }
        $object->setData('use_default', $useDefault);

        return $this;
    }

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

    protected function loadFaqCategoryRelation(AbstractModel $object)
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable(self::ITEM_FAQ_CATEGORY_TABLE), 'faq_category_id')
            ->where('item_id = ?', $object->getId());

        $categoryIds = $connection->fetchCol($select);
        $categoryIds = is_array($categoryIds) ? $categoryIds : [];

        $object->setData('category_id', $categoryIds);

        if (!empty($categoryIds)) {
            $object->setData('faq_categories', $categoryIds);
        }

        return $this;
    }
}
