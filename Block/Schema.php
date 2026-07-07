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
        if ($this->faqItems === null) {
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

            $product = $this->registry->registry('current_product');
            if ($product && $product->getId() && $this->faqHelper->isProductPageEnabled()) {
                $collection->addProductFilter($product->getId());

                $limit = (int)$this->faqHelper->getConfigValue(FaqHelper::XML_PATH_PRODUCT_LIMIT);
                if ($limit > 0) {
                    $collection->setPageSize($limit);
                }
            }

            elseif ($category = $this->registry->registry('current_category')) {
                if ($category->getId() && $this->faqHelper->isCategoryPageEnabled()) {
                    $collection->addCatalogCategoryFilter($category->getId());

                    $limit = (int)$this->faqHelper->getConfigValue(FaqHelper::XML_PATH_CATEGORY_LIMIT);
                    if ($limit > 0) {
                        $collection->setPageSize($limit);
                    }
                }
            }

            elseif ($page = $this->registry->registry('cms_page')) {
                if ($page->getId() && $this->faqHelper->isCmsPageEnabled()) {
                    $collection->addPageFilter($page->getId());
                }
            }

            else {
            }

            $this->faqItems = $collection;
        }

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
