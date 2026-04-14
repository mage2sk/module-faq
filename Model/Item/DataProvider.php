<?php
declare(strict_types=1);

namespace Panth\Faq\Model\Item;

use Panth\Faq\Model\ResourceModel\Item\CollectionFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Panth\Faq\Logger\Logger;
use Magento\Framework\App\ResourceConnection;

class DataProvider extends AbstractDataProvider
{
    protected $dataPersistor;
    protected $loadedData;
    protected $logger;
    protected $resourceConnection;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        DataPersistorInterface $dataPersistor,
        Logger $logger,
        ResourceConnection $resourceConnection,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->dataPersistor = $dataPersistor;
        $this->logger = $logger;
        $this->resourceConnection = $resourceConnection;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->logger->info('FAQ Item DataProvider initialized');
    }

    public function getData()
    {
        $this->logger->info('===== FAQ Item DataProvider getData() called =====');

        if (isset($this->loadedData)) {
            $this->logger->info('Returning cached loadedData', ['count' => count($this->loadedData)]);
            return $this->loadedData;
        }

        try {
            // Load all items from collection
            $items = $this->collection->getItems();
            $this->logger->info('Collection loaded', ['count' => count($items)]);

            $this->loadedData = [];
            foreach ($items as $item) {
                $itemData = $item->getData();

                // Load FAQ category assignments - use 'category_id' to match form field
                $itemData['category_id'] = $this->getFaqCategoryIds($item->getId());

                $this->loadedData[$item->getId()] = $itemData;
                $this->logger->info('Loaded item', [
                    'item_id' => $item->getId(),
                    'category_id' => $itemData['category_id']
                ]);
            }

            // Check for persisted data (from failed save attempts)
            $data = $this->dataPersistor->get('panth_faq_item');
            if (!empty($data)) {
                $this->logger->info('Found persisted data', ['has_id' => isset($data['item_id'])]);
                $item = $this->collection->getNewEmptyItem();
                $item->setData($data);
                $this->loadedData[$item->getId()] = $item->getData();
                $this->dataPersistor->clear('panth_faq_item');
            }

            $this->logger->info('Final loadedData', [
                'count' => count($this->loadedData),
                'keys' => array_keys($this->loadedData)
            ]);

            return $this->loadedData;
        } catch (\Exception $e) {
            $this->logger->error('FAQ Item DataProvider error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Get FAQ category IDs for an item
     *
     * @param int $itemId
     * @return array
     */
    protected function getFaqCategoryIds($itemId)
    {
        $connection = $this->resourceConnection->getConnection();
        $junctionTable = $this->resourceConnection->getTableName('panth_faq_item_faq_category');

        $select = $connection->select()
            ->from($junctionTable, ['faq_category_id'])
            ->where('item_id = ?', $itemId);

        return $connection->fetchCol($select);
    }
}
