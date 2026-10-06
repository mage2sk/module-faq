<?php
declare(strict_types=1);

namespace Panth\Faq\Block\Product;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Panth\Faq\Model\ResourceModel\Item\CollectionFactory;
use Magento\Framework\Registry;
use Panth\Faq\Helper\Data as FaqHelper;
use Magento\Catalog\Model\Product;
use Magento\Store\Model\StoreManagerInterface;

class Faq extends Template implements IdentityInterface
{
    protected $collectionFactory;

    protected $registry;

    protected $faqHelper;

    protected $storeManager;

    protected $faqItems = null;

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

    public function getCurrentProduct()
    {
        return $this->registry->registry('current_product');
    }

    public function getFaqItems()
    {
        if ($this->faqItems === null) {
            $product = $this->getCurrentProduct();

            if ($product && $product->getId()) {
                $storeId = $this->storeManager->getStore()->getId();

                $collection = $this->collectionFactory->create();
                $collection->addProductFilter($product->getId())
                    ->addActiveFilter()
                    ->addStoreFilter($storeId)
                    ->setOrder('sort_order', 'ASC');

                $limit = $this->getLimit();
                if ($limit > 0) {
                    $collection->setPageSize($limit);
                }

                $this->faqItems = $collection;
            } else {
                $this->faqItems = $this->collectionFactory->create();
            }
        }

        return $this->faqItems;
    }

    public function isEnabled(): bool
    {
        return $this->faqHelper->isProductPageEnabled();
    }

    public function getTitle(): string
    {
        return (string)$this->faqHelper->getConfigValue(
            FaqHelper::XML_PATH_PRODUCT_TITLE
        ) ?: __('Frequently Asked Questions')->render();
    }

    public function getLimit(): int
    {
        $limit = (int)$this->faqHelper->getConfigValue(
            FaqHelper::XML_PATH_PRODUCT_LIMIT
        );

        return $limit > 0 ? $limit : 0;
    }

    public function getFaqUrl(): string
    {
        $route = $this->faqHelper->getFaqRoute() ?: 'faq';
        return $this->getUrl($route);
    }

    public function shouldShowViewAllLink(): bool
    {
        $limit = $this->getLimit();
        return $limit > 0 && $this->getFaqItems()->getSize() > $limit;
    }

    public function getUncategorizedFaqItems()
    {
        $product = $this->getCurrentProduct();
        $entityId = $product ? (int)$product->getId() : 0;
        $collection = $this->collectionFactory->create();
        if ($entityId <= 0) {
            $collection->addFieldToFilter('main_table.item_id', ['null' => true]);
            return $collection;
        }

        $collection->addProductFilter($entityId)
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

        $shownIds = array_map('intval', $this->getFaqItems()->getColumnValues('item_id'));
        if (!empty($shownIds)) {
            $collection->addFieldToFilter('main_table.item_id', ['nin' => $shownIds]);
        }

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

    public function getIdentities()
    {
        $extra = [];
        $entity = $this->getCurrentProduct();
        if ($entity && $entity->getId()) {
            $extra[] = 'cat_p_' . (int)$entity->getId();
        }
        try {
            return \Panth\Faq\Model\CacheIdentities::fromItems(
                [$this->getFaqItems(), $this->getUncategorizedFaqItems()],
                $extra
            );
        } catch (\Throwable $e) {
            $extra[] = \Panth\Faq\Model\Item::CACHE_TAG;
            return $extra;
        }
    }
}
