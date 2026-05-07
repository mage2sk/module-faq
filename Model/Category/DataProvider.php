<?php
declare(strict_types=1);

namespace Panth\Faq\Model\Category;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Panth\Faq\Api\CategoryRepositoryInterface;
use Panth\Faq\Logger\Logger;
use Panth\Faq\Model\ResourceModel\Category as CategoryResource;
use Panth\Faq\Model\ResourceModel\Category\CollectionFactory;

/**
 * FAQ category edit-form DataProvider — store-scope aware.
 * Mirrors the Item DataProvider; see that file for the design notes.
 */
class DataProvider extends AbstractDataProvider
{
    protected $dataPersistor;
    protected $loadedData;
    protected $logger;
    protected CategoryRepositoryInterface $categoryRepository;
    protected RequestInterface $request;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        DataPersistorInterface $dataPersistor,
        Logger $logger,
        CategoryRepositoryInterface $categoryRepository,
        RequestInterface $request,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->dataPersistor = $dataPersistor;
        $this->logger = $logger;
        $this->categoryRepository = $categoryRepository;
        $this->request = $request;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * Field → fieldset map. See Item DataProvider for design rationale.
     * Keep in sync with view/adminhtml/ui_component/faq_category_form.xml.
     */
    private const SCOPED_FIELD_FIELDSETS = [
        'is_active'        => 'general',
        'name'             => 'general',
        'url_key'          => 'general',
        'description'      => 'general',
        'icon'             => 'general',
        'meta_title'       => 'search_engine_optimization',
        'meta_description' => 'search_engine_optimization',
        'meta_keywords'    => 'search_engine_optimization',
    ];

    public function getMeta()
    {
        $meta = parent::getMeta();
        $storeScopeId = $this->getStoreScopeIdFromRequest();
        if ($storeScopeId > 0) {
            foreach (self::SCOPED_FIELD_FIELDSETS as $field => $fieldset) {
                $meta[$fieldset]['children'][$field]['arguments']['data']['config']['service']['template']
                    = 'ui/form/element/helper/service';
                $meta[$fieldset]['children'][$field]['arguments']['data']['config']['imports']['isUseDefault']
                    = '${ $.provider }:data.use_default.' . $field;
            }
        }
        return $meta;
    }

    public function getData()
    {
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }

        try {
            $storeScopeId = $this->getStoreScopeIdFromRequest();

            $this->loadedData = [];
            foreach ($this->collection->getItems() as $category) {
                $categoryId = (int)$category->getId();

                if ($storeScopeId > 0) {
                    $scoped = $this->categoryRepository->getById($categoryId);
                    $scoped->setData('store_scope_id', $storeScopeId);
                    $scoped->getResource()->load($scoped, $categoryId);
                    $data = $scoped->getData();
                } else {
                    $data = $category->getData();
                }

                $data['store_scope_id'] = $storeScopeId;
                $this->loadedData[$categoryId] = $data;
            }

            $persisted = $this->dataPersistor->get('panth_faq_category');
            if (!empty($persisted)) {
                $tmp = $this->collection->getNewEmptyItem();
                $tmp->setData($persisted);
                $this->loadedData[$tmp->getId()] = $tmp->getData();
                $this->dataPersistor->clear('panth_faq_category');
            }

            return $this->loadedData;
        } catch (\Throwable $e) {
            $this->logger->error('FAQ Category DataProvider error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }
    }

    private function getStoreScopeIdFromRequest(): int
    {
        return (int)$this->request->getParam('store', 0);
    }
}
