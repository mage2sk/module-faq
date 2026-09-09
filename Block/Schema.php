<?php
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

    public function isEnabled(): bool
    {
        return $this->faqHelper->isEnabled() && $this->faqHelper->isSchemaEnabled();
    }

    public function getCurrentPageFaqs()
    {
        if ($this->faqItems !== null) {
            return $this->faqItems;
        }

        $storeId = $this->storeManager->getStore()->getId();
        $collection = $this->collectionFactory->create();
        $collection->addActiveFilter()
            ->addStoreFilter($storeId)
            ->setOrder('sort_order', 'ASC');

        $currentFaqItem = $this->registry->registry('current_faq_item');
        if ($currentFaqItem && $currentFaqItem->getId()) {
            $collection->addFieldToFilter('main_table.item_id', (int)$currentFaqItem->getId());
            $this->faqItems = $collection;
            return $this->faqItems;
        }

        $scopeMatched = false;
        $product = $this->registry->registry('current_product');
        $category = $this->registry->registry('current_category');
        $page = $this->registry->registry('cms_page');

        if ($product && $product->getId()) {
            if ($this->faqHelper->isProductPageEnabled()) {
                $collection->addProductFilter((int)$product->getId());
                $limit = (int)$this->faqHelper->getConfigValue(FaqHelper::XML_PATH_PRODUCT_LIMIT);
                if ($limit > 0) {
                    $collection->setPageSize($limit);
                }
                $scopeMatched = true;
            }
        } elseif ($category && $category->getId()) {
            if ($this->faqHelper->isCategoryPageEnabled()) {
                $collection->addCatalogCategoryFilter((int)$category->getId());
                $limit = (int)$this->faqHelper->getConfigValue(FaqHelper::XML_PATH_CATEGORY_LIMIT);
                if ($limit > 0) {
                    $collection->setPageSize($limit);
                }
                $scopeMatched = true;
            }
        } elseif ($page && $page->getId()) {
            if ($this->faqHelper->isCmsPageEnabled()) {
                $collection->addPageFilter((int)$page->getId());
                $scopeMatched = true;
            }
        } else {
            $fullActionName = (string)$this->getRequest()->getFullActionName();

            if ($fullActionName === 'faq_category_view') {
                $faqCategoryId = (int)$this->getRequest()->getParam('id');
                if ($faqCategoryId > 0) {
                    $collection->addCategoryFilter($faqCategoryId);
                    $scopeMatched = true;
                }
            } elseif ($fullActionName === 'faq_index_index') {
                $collection->addFaqCategoryAssignmentFilter();
                $scopeMatched = true;
            }
        }

        if (!$scopeMatched) {
            $collection->addFieldToFilter('main_table.item_id', 0);
        }

        $this->faqItems = $collection;

        return $this->faqItems;
    }

    public function getSchemaData(): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $faqs = $this->getCurrentPageFaqs();

        if (!$faqs || $faqs->getSize() === 0) {
            return null;
        }

        $maxQuestions = (int)$this->faqHelper->getConfigValue(FaqHelper::XML_PATH_SCHEMA_MAX_QUESTIONS);

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

            $cleanAnswer = strip_tags($answer);
            $cleanAnswer = html_entity_decode($cleanAnswer, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $cleanAnswer = trim($cleanAnswer);

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

            if ($maxQuestions > 0 && count($schemaData['mainEntity']) >= $maxQuestions) {
                break;
            }
        }

        if (empty($schemaData['mainEntity'])) {
            return null;
        }

        return json_encode($schemaData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    public function getFaqHelper(): \Panth\Faq\Helper\Data
    {
        return $this->faqHelper;
    }
}
