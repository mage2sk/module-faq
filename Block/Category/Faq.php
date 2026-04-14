<?php
/**
 * Category FAQ Block
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
use Panth\Faq\Model\ResourceModel\Item\CollectionFactory;
use Magento\Framework\Registry;
use Panth\Faq\Helper\Data as FaqHelper;
use Magento\Catalog\Model\Category;
use Magento\Store\Model\StoreManagerInterface;

class Faq extends Template
{
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var FaqHelper
     */
    protected $faqHelper;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Panth\Faq\Model\ResourceModel\Item\Collection|null
     */
    protected $faqCollection = null;

    /**
     * @param Context $context
     * @param CollectionFactory $collectionFactory
     * @param Registry $registry
     * @param FaqHelper $faqHelper
     * @param StoreManagerInterface $storeManager
     * @param array $data
     */
    public function __construct(
        Context $context,
        CollectionFactory $collectionFactory,
        Registry $registry,
        FaqHelper $faqHelper,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->registry = $registry;
        $this->faqHelper = $faqHelper;
        $this->storeManager = $storeManager;
        parent::__construct($context, $data);
    }

    /**
     * Get current category
     *
     * @return Category|null
     */
    public function getCurrentCategory()
    {
        return $this->registry->registry('current_category');
    }

    /**
     * Get FAQ items for current category
     *
     * @return \Panth\Faq\Model\ResourceModel\Item\Collection
     */
    public function getFaqItems()
    {
        if ($this->faqCollection === null) {
            $category = $this->getCurrentCategory();

            if ($category && $category->getId()) {
                $this->faqCollection = $this->collectionFactory->create();
                $this->faqCollection
                    ->addCatalogCategoryFilter($category->getId())
                    ->addActiveFilter()
                    ->addStoreFilter($this->storeManager->getStore()->getId())
                    ->setOrder('sort_order', 'ASC');

                $limit = $this->getLimit();
                if ($limit > 0) {
                    $this->faqCollection->setPageSize($limit);
                }
            } else {
                $this->faqCollection = $this->collectionFactory->create();
                $this->faqCollection->addFieldToFilter('item_id', ['null' => true]);
            }
        }

        return $this->faqCollection;
    }

    /**
     * Check if FAQ is enabled for category page
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->faqHelper->isCategoryPageEnabled();
    }

    /**
     * Get FAQ section title
     *
     * @return string
     */
    public function getTitle(): string
    {
        return (string)$this->faqHelper->getConfigValue(
            FaqHelper::XML_PATH_CATEGORY_TITLE
        ) ?: __('Frequently Asked Questions')->render();
    }

    /**
     * Get FAQ display limit
     *
     * @return int
     */
    public function getLimit(): int
    {
        $limit = (int)$this->faqHelper->getConfigValue(
            FaqHelper::XML_PATH_CATEGORY_LIMIT
        );

        return $limit > 0 ? $limit : 0;
    }

    /**
     * Get FAQ list page URL
     *
     * @return string
     */
    public function getFaqListUrl(): string
    {
        $route = $this->faqHelper->getFaqRoute() ?: 'faq';
        return $this->getUrl($route);
    }

    /**
     * Check if should display the block
     *
     * @return bool
     */
    public function canDisplay(): bool
    {
        return $this->isEnabled() && $this->getFaqItems()->getSize() > 0;
    }

    /**
     * Check if should show "View All FAQs" link
     *
     * @return bool
     */
    public function shouldShowViewAllLink(): bool
    {
        $limit = $this->getLimit();
        return $limit > 0 && $this->getFaqItems()->getSize() > $limit;
    }

    /**
     * Get cache key info
     *
     * @return array
     */
    public function getCacheKeyInfo()
    {
        $category = $this->getCurrentCategory();
        $categoryId = $category ? $category->getId() : 0;

        return array_merge(
            parent::getCacheKeyInfo(),
            [
                'category_id' => $categoryId,
                'store_id' => $this->storeManager->getStore()->getId()
            ]
        );
    }

    /**
     * Get cache lifetime
     *
     * @return int|null
     */
    protected function getCacheLifetime()
    {
        return 86400; // 1 day
    }

    /**
     * Get uncategorized FAQ items (not assigned to any category)
     *
     * @return \Panth\Faq\Model\ResourceModel\Item\Collection
     */
    public function getUncategorizedFaqItems()
    {
        $collection = $this->collectionFactory->create();
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

        // Apply limit if configured
        $limit = $this->getLimit();
        if ($limit > 0) {
            $collection->setPageSize($limit);
        }

        return $collection;
    }
}
