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
use Panth\Faq\Api\ItemRepositoryInterface;

class ItemActions extends Column
{
    const URL_PATH_EDIT = 'faq/item/edit';
    const URL_PATH_DELETE = 'faq/item/delete';
    const URL_PATH_VIEW = 'faq/index/view';

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ItemRepositoryInterface
     */
    protected $itemRepository;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param StoreManagerInterface $storeManager
     * @param ItemRepositoryInterface $itemRepository
     * @param ScopeConfigInterface $scopeConfig
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        StoreManagerInterface $storeManager,
        ItemRepositoryInterface $itemRepository,
        ScopeConfigInterface $scopeConfig,
        array $components = [],
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->storeManager = $storeManager;
        $this->itemRepository = $itemRepository;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                if (isset($item['item_id'])) {
                    $viewUrl = $this->getViewUrl($item['item_id']);

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
                                ['item_id' => $item['item_id']]
                            ),
                            'label' => __('Edit')
                        ],
                        'delete' => [
                            'href' => $this->urlBuilder->getUrl(
                                static::URL_PATH_DELETE,
                                ['item_id' => $item['item_id']]
                            ),
                            'label' => __('Delete'),
                            'confirm' => [
                                'title' => __('Delete FAQ Item'),
                                'message' => __('Are you sure you want to delete this FAQ item?')
                            ]
                        ]
                    ];
                }
            }
        }

        return $dataSource;
    }

    /**
     * Get frontend view URL for FAQ item
     *
     * @param int $itemId
     * @return string
     */
    protected function getViewUrl($itemId)
    {
        try {
            $item = $this->itemRepository->getById($itemId);
            $baseUrl = $this->storeManager->getStore()->getBaseUrl();
            $urlKey = $item->getUrlKey();

            // Get configured FAQ URL key
            $faqUrlKey = $this->scopeConfig->getValue(
                'panth_faq/general/faq_route',
                ScopeInterface::SCOPE_STORE
            );

            if (!$faqUrlKey) {
                $faqUrlKey = 'faq';
            }

            $faqUrlKey = trim($faqUrlKey, '/');

            if ($urlKey) {
                return $baseUrl . $faqUrlKey . '/item/' . $urlKey;
            }

            return $baseUrl . 'faq/index/view/id/' . $itemId;
        } catch (\Exception $e) {
            return '#';
        }
    }
}
