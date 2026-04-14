<?php
/**
 * FAQ Schema Block
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Panth\Faq\Model\ResourceModel\Item\CollectionFactory;
use Magento\Framework\Registry;
use Panth\Faq\Helper\Data as FaqHelper;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Category;
use Magento\Cms\Model\Page;

class Schema extends Template
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
     * Check if schema is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->faqHelper->isEnabled() && $this->faqHelper->isSchemaEnabled();
    }

    /**
     * Get FAQs for current page (product/category/CMS)
     *
     * @return \Panth\Faq\Model\ResourceModel\Item\Collection
     */
    public function getCurrentPageFaqs()
    {
        if ($this->faqItems === null) {
            $storeId = $this->storeManager->getStore()->getId();
            $collection = $this->collectionFactory->create();
            $collection->addActiveFilter()
                ->addStoreFilter($storeId)
                ->setOrder('sort_order', 'ASC');

            // Individual FAQ item page - emit schema for ONLY this item
            $currentFaqItem = $this->registry->registry('current_faq_item');
            if ($currentFaqItem && $currentFaqItem->getId()) {
                $collection->addFieldToFilter('main_table.item_id', (int)$currentFaqItem->getId());
                $this->faqItems = $collection;
                return $this->faqItems;
            }

            // Check if we're on a product page
            $product = $this->registry->registry('current_product');
            if ($product && $product->getId() && $this->faqHelper->isProductPageEnabled()) {
                $collection->addProductFilter($product->getId());

                // Apply limit if configured
                $limit = (int)$this->faqHelper->getConfigValue(FaqHelper::XML_PATH_PRODUCT_LIMIT);
                if ($limit > 0) {
                    $collection->setPageSize($limit);
                }
            }
            // Check if we're on a category page
            elseif ($category = $this->registry->registry('current_category')) {
                if ($category->getId() && $this->faqHelper->isCategoryPageEnabled()) {
                    $collection->addCatalogCategoryFilter($category->getId());

                    // Apply limit if configured
                    $limit = (int)$this->faqHelper->getConfigValue(FaqHelper::XML_PATH_CATEGORY_LIMIT);
                    if ($limit > 0) {
                        $collection->setPageSize($limit);
                    }
                }
            }
            // Check if we're on a CMS page
            elseif ($page = $this->registry->registry('cms_page')) {
                if ($page->getId() && $this->faqHelper->isCmsPageEnabled()) {
                    $collection->addPageFilter($page->getId());
                }
            }
            // Default FAQ listing page - get all active FAQs
            else {
                // No additional filters for main FAQ page
            }

            $this->faqItems = $collection;
        }

        return $this->faqItems;
    }

    /**
     * Generate JSON-LD schema data
     *
     * @return string|null
     */
    public function getSchemaData(): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $faqs = $this->getCurrentPageFaqs();

        if (!$faqs || $faqs->getSize() === 0) {
            return null;
        }

        $schemaData = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => []
        ];

        foreach ($faqs as $faq) {
            $question = $faq->getQuestion();
            $answer = $faq->getAnswer();

            if (empty($question) || empty($answer)) {
                continue;
            }

            // Strip HTML tags and decode entities for clean schema text
            $cleanAnswer = strip_tags($answer);
            $cleanAnswer = html_entity_decode($cleanAnswer, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $cleanAnswer = trim($cleanAnswer);

            // Skip if answer is empty after stripping
            if (empty($cleanAnswer)) {
                continue;
            }

            $schemaData['mainEntity'][] = [
                '@type' => 'Question',
                'name' => strip_tags($question),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $cleanAnswer
                ]
            ];
        }

        // Only return schema if we have at least one valid FAQ
        if (empty($schemaData['mainEntity'])) {
            return null;
        }

        return json_encode($schemaData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
