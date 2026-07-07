<?php
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
    protected $categoryCollectionFactory;

    protected $itemCollectionFactory;

    protected $faqHelper;

    protected $storeManager;

    protected $scopeConfig;

    protected $categoryCollection;

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

    public function getAllFaqItems()
    {
        $collection = $this->itemCollectionFactory->create();
        $collection
            ->addActiveFilter()
            ->addStoreFilter($this->storeManager->getStore()->getId())
            ->setOrder('sort_order', 'ASC');

        return $collection;
    }

    public function getSearchQuery()
    {
        return (string)$this->getRequest()->getParam('q', '');
    }

    public function getSelectedCategory()
    {
        return (int)$this->getRequest()->getParam('category', 0);
    }

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

    public function getFaqItemUrl($item)
    {
        return $this->getUrl('faq/index/view', ['id' => $item->getId()]);
    }

    public function getFaqCategoryUrl($category)
    {
        return $this->getUrl('faq/category/view', ['id' => $category->getId()]);
    }

    public function isSearchEnabled()
    {
        return (bool)$this->faqHelper->getConfigValue(FaqHelper::XML_PATH_SHOW_SEARCH);
    }

    public function isCategoryFilterEnabled()
    {
        return (bool)$this->faqHelper->getConfigValue(FaqHelper::XML_PATH_SHOW_CATEGORY_FILTER);
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

    public function getItemsPerPage()
    {
        return (int)$this->faqHelper->getConfigValue(FaqHelper::XML_PATH_ITEMS_PER_PAGE) ?: 20;
    }

    public function isDefaultOpen()
    {
        return (bool)$this->faqHelper->getConfigValue(FaqHelper::XML_PATH_DEFAULT_OPEN_FAQS);
    }

    public function getFaqMainUrl()
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

    public function getUncategorizedFaqItems()
    {
        $collection = $this->itemCollectionFactory->create();
        $collection
            ->addActiveFilter()
            ->addStoreFilter($this->storeManager->getStore()->getId())
            ->setOrder('sort_order', 'ASC');

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

    public function getFaqHelper(): \Panth\Faq\Helper\Data
    {
        return $this->faqHelper;
    }
}
