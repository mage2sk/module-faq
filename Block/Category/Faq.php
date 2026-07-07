<?php
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
    protected $collectionFactory;

    protected $registry;

    protected $faqHelper;

    protected $storeManager;

    protected $faqCollection = null;

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

    public function getCurrentCategory()
    {
        return $this->registry->registry('current_category');
    }

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

    public function isEnabled(): bool
    {
        return $this->faqHelper->isCategoryPageEnabled();
    }

    public function getTitle(): string
    {
        return (string)$this->faqHelper->getConfigValue(
            FaqHelper::XML_PATH_CATEGORY_TITLE
        ) ?: __('Frequently Asked Questions')->render();
    }

    public function getLimit(): int
    {
        $limit = (int)$this->faqHelper->getConfigValue(
            FaqHelper::XML_PATH_CATEGORY_LIMIT
        );

        return $limit > 0 ? $limit : 0;
    }

    public function getFaqListUrl(): string
    {
        $route = $this->faqHelper->getFaqRoute() ?: 'faq';
        return $this->getUrl($route);
    }

    public function canDisplay(): bool
    {
        return $this->isEnabled() && $this->getFaqItems()->getSize() > 0;
    }

    public function shouldShowViewAllLink(): bool
    {
        $limit = $this->getLimit();
        return $limit > 0 && $this->getFaqItems()->getSize() > $limit;
    }

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

    protected function getCacheLifetime()
    {
        return 86400;
    }

    public function getUncategorizedFaqItems()
    {
        $collection = $this->collectionFactory->create();
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

        $limit = $this->getLimit();
        if ($limit > 0) {
            $collection->setPageSize($limit);
        }

        return $collection;
    }

    public function getFaqHelper(): \Panth\Faq\Helper\Data
    {
        return $this->faqHelper;
    }
}
