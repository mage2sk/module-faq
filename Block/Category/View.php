<?php
/**
 * FAQ Category View Block
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Block\Category;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Faq\Api\CategoryRepositoryInterface;
use Panth\Faq\Model\ResourceModel\Item\CollectionFactory as ItemCollectionFactory;
use Panth\Faq\Helper\Data as FaqHelper;

class View extends Template
{
    /**
     * @var CategoryRepositoryInterface
     */
    protected $categoryRepository;

    /**
     * @var ItemCollectionFactory
     */
    protected $itemCollectionFactory;

    /**
     * @var FaqHelper
     */
    protected $faqHelper;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @param Context $context
     * @param CategoryRepositoryInterface $categoryRepository
     * @param ItemCollectionFactory $itemCollectionFactory
     * @param FaqHelper $faqHelper
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param array $data
     */
    public function __construct(
        Context $context,
        CategoryRepositoryInterface $categoryRepository,
        ItemCollectionFactory $itemCollectionFactory,
        FaqHelper $faqHelper,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->itemCollectionFactory = $itemCollectionFactory;
        $this->faqHelper = $faqHelper;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context, $data);
    }

    /**
     * Get current FAQ category
     *
     * @return \Panth\Faq\Api\Data\CategoryInterface|null
     */
    public function getCategory()
    {
        $categoryId = (int)$this->getRequest()->getParam('id');
        if (!$categoryId) {
            return null;
        }

        try {
            return $this->categoryRepository->getById($categoryId);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get FAQ items for current category
     *
     * @return \Panth\Faq\Model\ResourceModel\Item\Collection
     */
    public function getFaqItems()
    {
        $category = $this->getCategory();
        if (!$category) {
            return $this->itemCollectionFactory->create();
        }

        $collection = $this->itemCollectionFactory->create();
        $collection
            ->addActiveFilter()
            ->addStoreFilter($this->storeManager->getStore()->getId())
            ->addCategoryFilter($category->getId())
            ->setOrder('sort_order', 'ASC');

        return $collection;
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
            'panth_faq/general/url_key',
            ScopeInterface::SCOPE_STORE
        );

        if (!$faqUrlKey) {
            $faqUrlKey = 'faq';
        }

        $faqUrlKey = trim($faqUrlKey, '/');

        return $this->storeManager->getStore()->getBaseUrl() . $faqUrlKey;
    }

    /**
     * Check if search is enabled
     *
     * @return bool
     */
    public function isSearchEnabled()
    {
        return (bool)$this->faqHelper->getConfigValue(FaqHelper::XML_PATH_SHOW_SEARCH);
    }

    /**
     * Check if category description should be shown
     *
     * @return bool
     */
    public function showCategoryDescription()
    {
        return (bool)$this->faqHelper->getConfigValue(FaqHelper::XML_PATH_SHOW_CATEGORY_DESC);
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
     * Check if helpful voting is enabled
     *
     * @return bool
     */
    public function isHelpfulVotingEnabled()
    {
        return (bool)$this->faqHelper->getConfigValue(FaqHelper::XML_PATH_ENABLE_HELPFUL_VOTING);
    }

    /**
     * Check if FAQs should be open by default
     *
     * @return bool
     */
    public function isDefaultOpen()
    {
        return (bool)$this->faqHelper->getConfigValue(FaqHelper::XML_PATH_DEFAULT_OPEN_FAQS);
    }
}
