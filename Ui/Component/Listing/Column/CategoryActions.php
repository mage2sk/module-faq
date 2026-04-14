<?php
declare(strict_types=1);

namespace Panth\Faq\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Panth\Faq\Api\CategoryRepositoryInterface;

class CategoryActions extends Column
{
    const URL_PATH_EDIT = 'faq/category/edit';
    const URL_PATH_DELETE = 'faq/category/delete';

    protected $urlBuilder;
    protected $storeManager;
    protected $scopeConfig;
    protected $categoryRepository;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        CategoryRepositoryInterface $categoryRepository,
        array $components = [],
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->categoryRepository = $categoryRepository;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                if (isset($item['category_id'])) {
                    $viewUrl = $this->getViewUrl($item['category_id']);

                    $item[$this->getData('name')] = [
                        'view' => [
                            'href' => $viewUrl,
                            'label' => __('View'),
                            'target' => '_blank',
                            '__disableTmpl' => true
                        ],
                        'edit' => [
                            'href' => $this->urlBuilder->getUrl(
                                static::URL_PATH_EDIT,
                                ['category_id' => $item['category_id']]
                            ),
                            'label' => __('Edit')
                        ],
                        'delete' => [
                            'href' => $this->urlBuilder->getUrl(
                                static::URL_PATH_DELETE,
                                ['category_id' => $item['category_id']]
                            ),
                            'label' => __('Delete'),
                            'confirm' => [
                                'title' => __('Delete FAQ Category'),
                                'message' => __('Are you sure you want to delete this FAQ category?')
                            ]
                        ]
                    ];
                }
            }
        }

        return $dataSource;
    }

    protected function getViewUrl($categoryId)
    {
        try {
            $category = $this->categoryRepository->getById($categoryId);
            $baseUrl = $this->storeManager->getStore()->getBaseUrl();
            $urlKey = $category->getUrlKey();

            // Get configured FAQ URL key
            $faqUrlKey = $this->scopeConfig->getValue(
                'panth_faq/general/url_key',
                ScopeInterface::SCOPE_STORE
            );

            if (!$faqUrlKey) {
                $faqUrlKey = 'faq';
            }

            $faqUrlKey = trim($faqUrlKey, '/');

            if ($urlKey) {
                return $baseUrl . $faqUrlKey . '/category/' . $urlKey;
            }

            return $baseUrl . 'faq/category/view/id/' . $categoryId;
        } catch (\Exception $e) {
            return '#';
        }
    }
}
