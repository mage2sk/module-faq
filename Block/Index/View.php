<?php
/**
 * FAQ Item View Block
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Block\Index;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Registry;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\ResourceConnection;
use Panth\Faq\Api\ItemRepositoryInterface;
use Panth\Faq\Api\CategoryRepositoryInterface;
use Panth\Faq\Helper\Data as FaqHelper;

class View extends Template
{
    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var ItemRepositoryInterface
     */
    protected $itemRepository;

    /**
     * @var CategoryRepositoryInterface
     */
    protected $categoryRepository;

    /**
     * @var FaqHelper
     */
    protected $faqHelper;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ItemRepositoryInterface $itemRepository
     * @param CategoryRepositoryInterface $categoryRepository
     * @param FaqHelper $faqHelper
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param ResourceConnection $resourceConnection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ItemRepositoryInterface $itemRepository,
        CategoryRepositoryInterface $categoryRepository,
        FaqHelper $faqHelper,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        ResourceConnection $resourceConnection,
        array $data = []
    ) {
        $this->registry = $registry;
        $this->itemRepository = $itemRepository;
        $this->categoryRepository = $categoryRepository;
        $this->faqHelper = $faqHelper;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->resourceConnection = $resourceConnection;
        parent::__construct($context, $data);
    }

    /**
     * Get current FAQ item
     *
     * @return \Panth\Faq\Api\Data\ItemInterface|null
     */
    public function getFaqItem()
    {
        $itemId = (int)$this->getRequest()->getParam('id');
        if (!$itemId) {
            return null;
        }

        try {
            return $this->itemRepository->getById($itemId);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get back URL
     *
     * @return string
     */
    public function getBackUrl()
    {
        // Get configured FAQ URL key
        $faqUrlKey = $this->scopeConfig->getValue(
            'panth_faq/general/faq_route',
            ScopeInterface::SCOPE_STORE
        );

        if (!$faqUrlKey) {
            $faqUrlKey = 'faq';
        }

        $faqUrlKey = trim($faqUrlKey, '/');

        // Return direct URL using base URL + configured key
        return $this->storeManager->getStore()->getBaseUrl() . $faqUrlKey;
    }

    /**
     * Check if helpful voting is enabled
     *
     * @return bool
     */
    public function isHelpfulVotingEnabled()
    {
        return (bool)$this->faqHelper->getConfigValue(FaqHelper::XML_PATH_ENABLE_HELPFUL_VOTING);
    }

    /**
     * Check if view count should be shown
     *
     * @return bool
     */
    public function showViewCount()
    {
        return (bool)$this->faqHelper->getConfigValue(FaqHelper::XML_PATH_SHOW_VIEW_COUNT);
    }

    /**
     * Get categories for current FAQ item
     *
     * @return array
     */
    public function getFaqCategories()
    {
        $item = $this->getFaqItem();
        if (!$item) {
            return [];
        }

        try {
            $connection = $this->resourceConnection->getConnection();
            $select = $connection->select()
                ->from('panth_faq_item_faq_category', ['faq_category_id'])
                ->where('item_id = ?', $item->getId());

            $categoryIds = $connection->fetchCol($select);

            if (empty($categoryIds)) {
                return [];
            }

            $categories = [];
            foreach ($categoryIds as $categoryId) {
                try {
                    $category = $this->categoryRepository->getById($categoryId);
                    if ($category && $category->getIsActive()) {
                        $categories[] = $category;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            return $categories;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get category URL
     *
     * @param \Panth\Faq\Api\Data\CategoryInterface $category
     * @return string
     */
    public function getCategoryUrl($category)
    {
        // Get configured FAQ URL key
        $faqUrlKey = $this->scopeConfig->getValue(
            'panth_faq/general/faq_route',
            ScopeInterface::SCOPE_STORE
        );

        if (!$faqUrlKey) {
            $faqUrlKey = 'faq';
        }

        $faqUrlKey = trim($faqUrlKey, '/');
        $baseUrl = $this->storeManager->getStore()->getBaseUrl();

        if ($category->getUrlKey()) {
            return $baseUrl . $faqUrlKey . '/category/' . $category->getUrlKey();
        }

        return $baseUrl . $faqUrlKey;
    }
}
