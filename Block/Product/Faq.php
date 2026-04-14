<?php
/**
 * Product FAQ Block
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Block\Product;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Panth\Faq\Model\ResourceModel\Item\CollectionFactory;
use Magento\Framework\Registry;
use Panth\Faq\Helper\Data as FaqHelper;
use Magento\Catalog\Model\Product;
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
    protected $faqItems = null;

    /**
     * Constructor
     *
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
     * Get current product
     *
     * @return Product|null
     */
    public function getCurrentProduct()
    {
        return $this->registry->registry('current_product');
    }

    /**
     * Get FAQ items assigned to current product
     *
     * @return \Panth\Faq\Model\ResourceModel\Item\Collection
     */
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

                // Apply limit if configured
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

    /**
     * Check if FAQ is enabled for product page
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->faqHelper->isProductPageEnabled();
    }

    /**
     * Get product page FAQ title from configuration
     *
     * @return string
     */
    public function getTitle(): string
    {
        return (string)$this->faqHelper->getConfigValue(
            FaqHelper::XML_PATH_PRODUCT_TITLE
        ) ?: __('Frequently Asked Questions')->render();
    }

    /**
     * Get limit from configuration
     *
     * @return int
     */
    public function getLimit(): int
    {
        $limit = (int)$this->faqHelper->getConfigValue(
            FaqHelper::XML_PATH_PRODUCT_LIMIT
        );

        return $limit > 0 ? $limit : 0;
    }

    /**
     * Get FAQ main page URL
     *
     * @return string
     */
    public function getFaqUrl(): string
    {
        $route = $this->faqHelper->getFaqRoute() ?: 'faq';
        return $this->getUrl($route);
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
