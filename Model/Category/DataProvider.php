<?php
declare(strict_types=1);

namespace Panth\Faq\Model\Category;

use Panth\Faq\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Panth\Faq\Logger\Logger;

class DataProvider extends AbstractDataProvider
{
    protected $dataPersistor;
    protected $loadedData;
    protected $logger;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        DataPersistorInterface $dataPersistor,
        Logger $logger,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->dataPersistor = $dataPersistor;
        $this->logger = $logger;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->logger->info('FAQ Category DataProvider initialized');
    }

    public function getData()
    {
        $this->logger->info('===== FAQ Category DataProvider getData() called =====');

        if (isset($this->loadedData)) {
            $this->logger->info('Returning cached loadedData', ['count' => count($this->loadedData)]);
            return $this->loadedData;
        }

        try {
            // Load all categories from collection
            $items = $this->collection->getItems();
            $this->logger->info('Collection loaded', ['count' => count($items)]);

            $this->loadedData = [];
            foreach ($items as $category) {
                $this->loadedData[$category->getId()] = $category->getData();
                $this->logger->info('Loaded category', ['category_id' => $category->getId()]);
            }

            // Check for persisted data (from failed save attempts)
            $data = $this->dataPersistor->get('panth_faq_category');
            if (!empty($data)) {
                $this->logger->info('Found persisted data', ['has_id' => isset($data['category_id'])]);
                $category = $this->collection->getNewEmptyItem();
                $category->setData($data);
                $this->loadedData[$category->getId()] = $category->getData();
                $this->dataPersistor->clear('panth_faq_category');
            }

            $this->logger->info('Final loadedData', [
                'count' => count($this->loadedData),
                'keys' => array_keys($this->loadedData)
            ]);

            return $this->loadedData;
        } catch (\Exception $e) {
            $this->logger->error('FAQ Category DataProvider error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }
}
