<?php
/**
 * FAQ List Block
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
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Faq\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Panth\Faq\Model\ResourceModel\Item\CollectionFactory as ItemCollectionFactory;
use Panth\Faq\Helper\Data as FaqHelper;

class Index extends Template
{
    /**
     * @var CategoryCollectionFactory
     */
    protected $categoryCollectionFactory;

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
     * @var \Panth\Faq\Model\ResourceModel\Category\Collection|null
     */
    protected $categoryCollection;

    /**
     * @param Context $context
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param ItemCollectionFactory $itemCollectionFactory
     * @param FaqHelper $faqHelper
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param array $data
     */
    public function __construct(
        Context $context,
        CategoryCollectionFactory $categoryCollectionFactory,
        ItemCollectionFactory $itemCollectionFactory,
        FaqHelper $faqHelper,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->itemCollectionFactory = $itemCollectionFactory;
        $this->faqHelper = $faqHelper;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context, $data);
    }

    /**
     * Get all active FAQ categories
     *
     * @return \Panth\Faq\Model\ResourceModel\Category\Collection
     */
    public function getFaqCategories()
    {
        if ($this->categoryCollection === null) {
            $this->categoryCollection = $this->categoryCollectionFactory->create();
            $this->categoryCollection
                ->addActiveFilter()
                ->addStoreFilter($this->storeManager->getStore()->getId())
                ->setOrder('sort_order', 'ASC');
        }

        return $this->categoryCollection;
    }

    /**
     * Get FAQ items by category
     *
     * @param int $categoryId
     * @return \Panth\Faq\Model\ResourceModel\Item\Collection
     */
    public function getFaqItemsByCategory($categoryId)
    {
        $collection = $this->itemCollectionFactory->create();
        $collection
            ->addActiveFilter()
            ->addStoreFilter($this->storeManager->getStore()->getId())
            ->addCategoryFilter($categoryId)
            ->setOrder('sort_order', 'ASC');

        return $collection;
    }

    /**
     * Get all FAQ items
     *
     * @return \Panth\Faq\Model\ResourceModel\Item\Collection
     */
    public function getAllFaqItems()
    {
        $collection = $this->itemCollectionFactory->create();
        $collection
            ->addActiveFilter()
            ->addStoreFilter($this->storeManager->getStore()->getId())
            ->setOrder('sort_order', 'ASC');

        return $collection;
    }

    /**
     * Get search query from request
     *
     * @return string
     */
    public function getSearchQuery()
    {
        return (string)$this->getRequest()->getParam('q', '');
    }

    /**
     * Get selected category from request
     *
     * @return int
     */
    public function getSelectedCategory()
    {
        return (int)$this->getRequest()->getParam('category', 0);
    }

    /**
     * Search FAQs
     *
     * @param string $query
     * @return \Panth\Faq\Model\ResourceModel\Item\Collection
     */
    public function search($query)
    {
        $collection = $this->itemCollectionFactory->create();
        $collection
            ->addActiveFilter()
            ->addStoreFilter($this->storeManager->getStore()->getId())
            ->addFieldToFilter(
                ['question', 'answer'],
                [
                    ['like' => '%' . $query . '%'],
                    ['like' => '%' . $query . '%']
                ]
            )
            ->setOrder('sort_order', 'ASC');

        return $collection;
    }

    /**
     * Get FAQ item URL
     *
     * @param \Panth\Faq\Model\Item $item
     * @return string
     */
    public function getFaqItemUrl($item)
    {
        return $this->getUrl('faq/index/view', ['id' => $item->getId()]);
    }

    /**
     * Get FAQ category URL
     *
     * @param \Panth\Faq\Model\Category $category
     * @return string
     */
    public function getFaqCategoryUrl($category)
    {
        return $this->getUrl('faq/category/view', ['id' => $category->getId()]);
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
     * Check if category filter is enabled
     *
     * @return bool
     */
    public function isCategoryFilterEnabled()
    {
        return (bool)$this->faqHelper->getConfigValue(FaqHelper::XML_PATH_SHOW_CATEGORY_FILTER);
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
     * Get items per page
     *
     * @return int
     */
    public function getItemsPerPage()
    {
        return (int)$this->faqHelper->getConfigValue(FaqHelper::XML_PATH_ITEMS_PER_PAGE) ?: 20;
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

    /**
     * Get FAQ main page URL
     *
     * @return string
     */
    public function getFaqMainUrl()
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

        return $this->storeManager->getStore()->getBaseUrl() . $faqUrlKey;
    }

    /**
     * Get uncategorized FAQ items (not assigned to any category)
     *
     * @return \Panth\Faq\Model\ResourceModel\Item\Collection
     */
    public function getUncategorizedFaqItems()
    {
        $collection = $this->itemCollectionFactory->create();
        $collection
            ->addActiveFilter()
            ->addStoreFilter($this->storeManager->getStore()->getId())
            ->setOrder('sort_order', 'ASC');

        // Filter items that don't have any category assignment
        $collection->getSelect()
            ->joinLeft(
                ['faq_cat' => $collection->getTable('panth_faq_item_faq_category')],
                'main_table.item_id = faq_cat.item_id',
                []
            )
            ->where('faq_cat.faq_category_id IS NULL')
            ->group('main_table.item_id');

        return $collection;
    }
}
