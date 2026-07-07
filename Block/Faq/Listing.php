<?php
declare(strict_types=1);

namespace Panth\Faq\Block\Faq;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Panth\Faq\Helper\Data as FaqHelper;
use Panth\Faq\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Panth\Faq\Model\ResourceModel\Item\CollectionFactory as ItemCollectionFactory;

class Listing extends Template
{
    protected $categoryCollectionFactory;

    protected $itemCollectionFactory;

    protected $categories;

    protected $faqHelper;

    public function __construct(
        Context $context,
        CategoryCollectionFactory $categoryCollectionFactory,
        ItemCollectionFactory $itemCollectionFactory,
        FaqHelper $faqHelper,
        array $data = []
    ) {
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->itemCollectionFactory = $itemCollectionFactory;
        $this->faqHelper = $faqHelper;
        parent::__construct($context, $data);
    }

    public function getFaqHelper(): FaqHelper
    {
        return $this->faqHelper;
    }

    public function getCategories()
    {
        if ($this->categories === null) {
            $this->categories = $this->categoryCollectionFactory->create()
                ->addFieldToFilter('is_active', 1)
                ->setOrder('sort_order', 'ASC');
        }
        return $this->categories;
    }

    public function getCategoryItems($categoryId)
    {
        return $this->itemCollectionFactory->create()
            ->addFieldToFilter('category_id', $categoryId)
            ->addFieldToFilter('is_active', 1)
            ->setOrder('sort_order', 'ASC');
    }

    public function getItemAccordionId($categoryId, $itemId)
    {
        return 'faq-item-' . $categoryId . '-' . $itemId;
    }

    public function getCategoryAccordionId($categoryId)
    {
        return 'faq-category-' . $categoryId;
    }

    public function hasItems($categoryId)
    {
        return $this->getCategoryItems($categoryId)->getSize() > 0;
    }

    public function escapeHtmlAttr($string, $escapeSingleQuote = true)
    {
        return $this->escaper->escapeHtmlAttr($string, $escapeSingleQuote);
    }
}
