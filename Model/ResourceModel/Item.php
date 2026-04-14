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

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Model\AbstractModel;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;

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

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var EventManager
     */
    protected $eventManager;

    /**
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param EventManager $eventManager
     * @param string $connectionName
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        EventManager $eventManager,
        $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
        $this->storeManager = $storeManager;
        $this->eventManager = $eventManager;
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
     * Save store relation
     *
     * @param AbstractModel $object
     * @return $this
     */
    protected function _afterSave(AbstractModel $object)
    {
        $this->saveStoreRelation($object);
        $this->saveProductRelation($object);
        $this->saveCatalogCategoryRelation($object);
        $this->savePageRelation($object);
        $this->saveFaqCategoryRelation($object);

        // Dispatch event for URL rewrite generation
        $this->eventManager->dispatch('panth_faq_item_save_after', ['item' => $object]);

        return parent::_afterSave($object);
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
        $this->loadProductRelation($object);
        $this->loadCatalogCategoryRelation($object);
        $this->loadPageRelation($object);
        $this->loadFaqCategoryRelation($object);

        return parent::_afterLoad($object);
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
