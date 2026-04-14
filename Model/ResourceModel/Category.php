<?php
/**
 * FAQ Category Resource Model
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

class Category extends AbstractDb
{
    /**
     * Store table name
     */
    const CATEGORY_STORE_TABLE = 'panth_faq_category_store';

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
        $this->_init('panth_faq_category', 'category_id');
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

        // Dispatch event for URL rewrite generation
        $this->eventManager->dispatch('panth_faq_category_save_after', ['category' => $object]);

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
            $table = $this->getTable(self::CATEGORY_STORE_TABLE);

            $connection->delete($table, ['category_id = ?' => $object->getId()]);

            $insertData = [];
            foreach ((array)$stores as $storeId) {
                $insertData[] = [
                    'category_id' => $object->getId(),
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
     * Load store relation
     *
     * @param AbstractModel $object
     * @return $this
     */
    protected function _afterLoad(AbstractModel $object)
    {
        $this->loadStoreRelation($object);
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
            ->from($this->getTable(self::CATEGORY_STORE_TABLE), 'store_id')
            ->where('category_id = ?', $object->getId());

        $stores = $connection->fetchCol($select);
        $object->setData('store_id', $stores);
        $object->setData('stores', $stores);

        return $this;
    }
}
