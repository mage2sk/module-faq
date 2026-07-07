<?php
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
    protected $_template = 'Panth_Faq::widget/faq.phtml';

    protected $collectionFactory;

    protected $faqHelper;

    protected $storeManager;

    protected $faqItems = null;

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

    public function getFaqItems()
    {
        if ($this->faqItems === null) {
            $storeId = $this->storeManager->getStore()->getId();
            $collection = $this->collectionFactory->create();

            $collection->addActiveFilter()
                ->addStoreFilter($storeId);

            $faqItems = $this->getData('faq_items');
            if ($faqItems) {
                $itemIds = explode(',', $faqItems);
                $itemIds = array_map('trim', $itemIds);
                $itemIds = array_filter($itemIds);
                if (!empty($itemIds)) {
                    $collection->addFieldToFilter('main_table.item_id', ['in' => $itemIds]);

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

            $limit = $this->getLimit();
            if ($limit > 0) {
                $collection->setPageSize($limit);
            }

            $this->faqItems = $collection;
        }

        return $this->faqItems;
    }

    public function getTitle(): string
    {
        $title = $this->getData('title');
        if ($title) {
            return $title;
        }
        return __('Frequently Asked Questions')->render();
    }

    public function getLimit(): int
    {
        $limit = (int)$this->getData('limit');
        return $limit > 0 ? $limit : 0;
    }

    public function shouldDisplay(): bool
    {
        if (!$this->faqHelper->isEnabled()) {
            return false;
        }

        $faqItems = $this->getFaqItems();
        return $faqItems && $faqItems->getSize() > 0;
    }

    public function getFaqUrl(): string
    {
        $route = $this->faqHelper->getFaqRoute() ?: 'faq';
        return $this->getUrl($route);
    }

    public function shouldShowViewAllLink(): bool
    {
        $showViewAll = $this->getData('show_view_all');
        if ($showViewAll === null || $showViewAll === '') {
            return true;
        }
        return (bool)$showViewAll;
    }

    public function getFaqHelper(): \Panth\Faq\Helper\Data
    {
        return $this->faqHelper;
    }
}
