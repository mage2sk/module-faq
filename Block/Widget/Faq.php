<?php
/**
 * FAQ Widget Block
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Block\Widget;

use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;
use Magento\Framework\View\Element\Template\Context;
use Panth\Faq\Model\ResourceModel\Item\CollectionFactory;
use Panth\Faq\Helper\Data as FaqHelper;
use Magento\Store\Model\StoreManagerInterface;

class Faq extends Template implements BlockInterface
{
    /**
     * @var string
     */
    protected $_template = 'Panth_Faq::widget/faq.phtml';

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

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
     * @param FaqHelper $faqHelper
     * @param StoreManagerInterface $storeManager
     * @param array $data
     */
    public function __construct(
        Context $context,
        CollectionFactory $collectionFactory,
        FaqHelper $faqHelper,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->faqHelper = $faqHelper;
        $this->storeManager = $storeManager;
        parent::__construct($context, $data);
    }

    /**
     * Get FAQ items based on widget configuration
     *
     * @return \Panth\Faq\Model\ResourceModel\Item\Collection
     */
    public function getFaqItems()
    {
        if ($this->faqItems === null) {
            $storeId = $this->storeManager->getStore()->getId();
            $collection = $this->collectionFactory->create();

            $collection->addActiveFilter()
                ->addStoreFilter($storeId);

            // Filter by FAQ items if provided
            $faqItems = $this->getData('faq_items');
            if ($faqItems) {
                $itemIds = explode(',', $faqItems);
                $itemIds = array_map('trim', $itemIds);
                $itemIds = array_filter($itemIds);
                if (!empty($itemIds)) {
                    $collection->addFieldToFilter('main_table.item_id', ['in' => $itemIds]);
                    // Order by the specified order in the widget config
                    $orderList = implode(',', $itemIds);
                    $collection->getSelect()->order(
                        new \Magento\Framework\DB\Sql\Expression("FIELD(main_table.item_id, $orderList)")
                    );
                } else {
                    $collection->setOrder('sort_order', 'ASC');
                }
            } else {
                $collection->setOrder('sort_order', 'ASC');
            }

            // Apply limit if configured
            $limit = $this->getLimit();
            if ($limit > 0) {
                $collection->setPageSize($limit);
            }

            $this->faqItems = $collection;
        }

        return $this->faqItems;
    }

    /**
     * Get widget title
     *
     * @return string
     */
    public function getTitle(): string
    {
        $title = $this->getData('title');
        if ($title) {
            return $title;
        }
        return __('Frequently Asked Questions')->render();
    }

    /**
     * Get limit from widget configuration
     *
     * @return int
     */
    public function getLimit(): int
    {
        $limit = (int)$this->getData('limit');
        return $limit > 0 ? $limit : 0;
    }

    /**
     * Check if widget should be displayed
     *
     * @return bool
     */
    public function shouldDisplay(): bool
    {
        if (!$this->faqHelper->isEnabled()) {
            return false;
        }

        $faqItems = $this->getFaqItems();
        return $faqItems && $faqItems->getSize() > 0;
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
        $showViewAll = $this->getData('show_view_all');
        if ($showViewAll === null || $showViewAll === '') {
            return true; // Default to showing the link
        }
        return (bool)$showViewAll;
    }
}
