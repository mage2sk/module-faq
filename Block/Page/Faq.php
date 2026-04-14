<?php
/**
 * CMS Page FAQ Block
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Block\Page;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Panth\Faq\Model\ResourceModel\Item\CollectionFactory;
use Magento\Cms\Model\Page;
use Magento\Framework\App\RequestInterface;
use Panth\Faq\Helper\Data as FaqHelper;
use Magento\Store\Model\StoreManagerInterface;

class Faq extends Template
{
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var Page
     */
    protected $page;

    /**
     * @var RequestInterface
     */
    protected $request;

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
     * @param Page $page
     * @param RequestInterface $request
     * @param FaqHelper $faqHelper
     * @param StoreManagerInterface $storeManager
     * @param array $data
     */
    public function __construct(
        Context $context,
        CollectionFactory $collectionFactory,
        Page $page,
        RequestInterface $request,
        FaqHelper $faqHelper,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->page = $page;
        $this->request = $request;
        $this->faqHelper = $faqHelper;
        $this->storeManager = $storeManager;
        parent::__construct($context, $data);
    }

    /**
     * Get current CMS page
     *
     * @return Page|null
     */
    public function getCurrentPage()
    {
        $pageId = $this->request->getParam('page_id');
        if ($pageId && $this->page->getId() != $pageId) {
            $this->page->load($pageId);
        }
        return $this->page->getId() ? $this->page : null;
    }

    /**
     * Get FAQ items assigned to current CMS page
     *
     * @return \Panth\Faq\Model\ResourceModel\Item\Collection
     */
    public function getFaqItems()
    {
        if ($this->faqItems === null) {
            $page = $this->getCurrentPage();

            if ($page && $page->getId()) {
                $storeId = $this->storeManager->getStore()->getId();

                $collection = $this->collectionFactory->create();
                $collection->addPageFilter($page->getId())
                    ->addActiveFilter()
                    ->addStoreFilter($storeId)
                    ->setOrder('sort_order', 'ASC');

                $this->faqItems = $collection;
            } else {
                $this->faqItems = $this->collectionFactory->create();
            }
        }

        return $this->faqItems;
    }

    /**
     * Check if FAQ is enabled for CMS pages
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->faqHelper->isCmsPageEnabled();
    }

    /**
     * Get CMS page FAQ title from configuration
     *
     * @return string
     */
    public function getTitle(): string
    {
        return (string)$this->faqHelper->getConfigValue(
            FaqHelper::XML_PATH_CMS_TITLE
        ) ?: __('Frequently Asked Questions')->render();
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

        return $collection;
    }
}
