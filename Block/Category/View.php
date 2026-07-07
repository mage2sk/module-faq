<?php
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
    protected $categoryRepository;

    protected $itemCollectionFactory;

    protected $faqHelper;

    protected $storeManager;

    protected $scopeConfig;

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

    public function getBackUrl()
    {
        $faqUrlKey = $this->scopeConfig->getValue(
            'panth_faq/general/faq_route',
            ScopeInterface::SCOPE_STORE
        );

        if (!$faqUrlKey) {
            $faqUrlKey = 'faq';
        }

        $faqUrlKey = trim($faqUrlKey, '/');

        return $this->storeManager->getStore()->getBaseUrl() . $faqUrlKey;
    }

    public function isSearchEnabled()
    {
        return (bool)$this->faqHelper->getConfigValue(FaqHelper::XML_PATH_SHOW_SEARCH);
    }

    public function showCategoryDescription()
    {
        return (bool)$this->faqHelper->getConfigValue(FaqHelper::XML_PATH_SHOW_CATEGORY_DESC);
    }

    public function showViewCount()
    {
        return (bool)$this->faqHelper->getConfigValue(FaqHelper::XML_PATH_SHOW_VIEW_COUNT);
    }

    public function isHelpfulVotingEnabled()
    {
        return (bool)$this->faqHelper->getConfigValue(FaqHelper::XML_PATH_ENABLE_HELPFUL_VOTING);
    }

    public function isDefaultOpen()
    {
        return (bool)$this->faqHelper->getConfigValue(FaqHelper::XML_PATH_DEFAULT_OPEN_FAQS);
    }

    public function getFaqHelper(): \Panth\Faq\Helper\Data
    {
        return $this->faqHelper;
    }
}
