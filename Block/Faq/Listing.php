<?php
/**
 * FAQ Listing Block
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Block\Faq;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Panth\Faq\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Panth\Faq\Model\ResourceModel\Item\CollectionFactory as ItemCollectionFactory;

class Listing extends Template
{
    /**
     * @var CategoryCollectionFactory
     */
    protected $categoryCollectionFactory;

    /**
     * @var ItemCollectionFactory
     */
    protected $itemCollectionFactory;

    /**
     * @var \Panth\Faq\Model\ResourceModel\Category\Collection
     */
    protected $categories;

    /**
     * @param Context $context
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param ItemCollectionFactory $itemCollectionFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        CategoryCollectionFactory $categoryCollectionFactory,
        ItemCollectionFactory $itemCollectionFactory,
        array $data = []
    ) {
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->itemCollectionFactory = $itemCollectionFactory;
        parent::__construct($context, $data);
    }

    /**
     * Get all active FAQ categories
     *
     * @return \Panth\Faq\Model\ResourceModel\Category\Collection
     */
    public function getCategories()
    {
        if ($this->categories === null) {
            $this->categories = $this->categoryCollectionFactory->create()
                ->addFieldToFilter('is_active', 1)
                ->setOrder('sort_order', 'ASC');
        }
        return $this->categories;
    }

    /**
     * Get FAQ items for a specific category
     *
     * @param int $categoryId
     * @return \Panth\Faq\Model\ResourceModel\Item\Collection
     */
    public function getCategoryItems($categoryId)
    {
        return $this->itemCollectionFactory->create()
            ->addFieldToFilter('category_id', $categoryId)
            ->addFieldToFilter('is_active', 1)
            ->setOrder('sort_order', 'ASC');
    }

    /**
     * Get unique item ID for accordion
     *
     * @param int $categoryId
     * @param int $itemId
     * @return string
     */
    public function getItemAccordionId($categoryId, $itemId)
    {
        return 'faq-item-' . $categoryId . '-' . $itemId;
    }

    /**
     * Get unique category ID for accordion
     *
     * @param int $categoryId
     * @return string
     */
    public function getCategoryAccordionId($categoryId)
    {
        return 'faq-category-' . $categoryId;
    }

    /**
     * Check if category has items
     *
     * @param int $categoryId
     * @return bool
     */
    public function hasItems($categoryId)
    {
        return $this->getCategoryItems($categoryId)->getSize() > 0;
    }

    /**
     * Escape HTML
     *
     * @param string $string
     * @param bool $escapeSingleQuote
     * @return string
     */
    public function escapeHtmlAttr($string, $escapeSingleQuote = true)
    {
        return $this->escaper->escapeHtmlAttr($string, $escapeSingleQuote);
    }
}
