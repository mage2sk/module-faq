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
            $overrides = $this->getStoreOverrideRow($storeScopeId);
            foreach (self::SCOPED_FIELD_FIELDSETS as $field => $fieldset) {
                $meta[$fieldset]['children'][$field]['arguments']['data']['config']['service']['template']
                    = 'ui/form/element/helper/service';
                $meta[$fieldset]['children'][$field]['arguments']['data']['config']['imports']['isUseDefault']
                    = '${ $.provider }:data.use_default.' . $field;
                $meta[$fieldset]['children'][$field]['arguments']['data']['config']['disabled']
                    = !isset($overrides[$field]);
            }
        }
        return $meta;
    }

    private function getStoreOverrideRow(int $storeScopeId): array
    {
        $entityId = (int)$this->request->getParam($this->getRequestFieldName());
        if ($entityId <= 0) {
            return [];
        }
        try {
            $resource = $this->categoryRepository->getById($entityId)->getResource();
            $row = $resource->getStoreOverrideRow($entityId, $storeScopeId);
        } catch (\Throwable $e) {
            return [];
        }
        return is_array($row) ? $row : [];
    }

    private function getIconUploaderValue($icon): ?array
    {
        if (!is_string($icon) || $icon === '' || str_starts_with($icon, '[')) {
            return null;
        }
        $name = basename($icon);
        $mediaUrl = \Magento\Framework\App\ObjectManager::getInstance()
            ->get(\Magento\Store\Model\StoreManagerInterface::class)
            ->getStore()
            ->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);

        $value = ['name' => $name, 'file' => $name, 'url' => $mediaUrl . 'panth/faq/category/' . rawurlencode($name), 'size' => 0];
        try {
            $media = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\Magento\Framework\Filesystem::class)
                ->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);
            $path = 'panth/faq/category/' . $name;
            if ($media->isFile($path)) {
                $stat = $media->stat($path);
                $value['size'] = (int) ($stat['size'] ?? 0);
                if (function_exists('mime_content_type')) {
                    $value['type'] = (string) mime_content_type($media->getAbsolutePath($path));
                }
            }
        } catch (\Exception $e) {
            $value['size'] = 0;
        }

        return [$value];
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
                $data['icon'] = $this->getIconUploaderValue($data['icon'] ?? null);
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
