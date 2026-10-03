<?php
declare(strict_types=1);

namespace Panth\Faq\Block\Adminhtml\Item\Edit\Tab;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\Data\Tree\Node;
use Magento\Catalog\Model\CategoryFactory;

class CategoryTree extends Template
{
    protected $_template = 'Panth_Faq::item/edit/tab/category_tree.phtml';

    protected $registry;

    protected $categoryCollectionFactory;

    protected $categoryFactory;

    public function __construct(
        Context $context,
        Registry $registry,
        CategoryCollectionFactory $categoryCollectionFactory,
        CategoryFactory $categoryFactory,
        array $data = []
    ) {
        $this->registry = $registry;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->categoryFactory = $categoryFactory;
        parent::__construct($context, $data);
    }

    public function getItem()
    {
        return $this->registry->registry('panth_faq_item');
    }

    public function getSelectedCategories(): array
    {
        $item = $this->getItem();
        if (!$item || !$item->getId()) {
            return [];
        }

        $categories = $item->getData('catalog_categories');

        if (empty($categories)) {
            return [];
        }

        if (is_string($categories)) {
            $decoded = json_decode($categories, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_map('intval', $decoded);
            }
            return array_map('intval', explode(',', $categories));
        }

        if (is_array($categories)) {
            return array_map('intval', $categories);
        }

        return [];
    }

    public function getCategoryTreeJson(): string
    {
        try {
            $collection = $this->categoryCollectionFactory->create();
            $collection->addAttributeToSelect(['name', 'is_active', 'level', 'path', 'parent_id'])
                ->addFieldToFilter('is_active', 1)
                ->addFieldToFilter('level', ['gt' => 1])
                ->setOrder('level', 'ASC')
                ->setOrder('sort_order', 'ASC');

            $categoriesArray = [];
            $categoryMap = [];

            foreach ($collection as $category) {
                $categoryData = [
                    'id' => (int)$category->getId(),
                    'text' => $category->getName(),
                    'level' => (int)$category->getLevel(),
                    'parent_id' => (int)$category->getParentId(),
                    'children' => []
                ];
                $categoryMap[$category->getId()] = $categoryData;
            }

            foreach ($categoryMap as $id => $categoryData) {
                $parentId = $categoryData['parent_id'];

                if ($categoryData['level'] == 2) {
                    $categoriesArray[] = &$categoryMap[$id];
                } elseif (isset($categoryMap[$parentId])) {
                    $categoryMap[$parentId]['children'][] = &$categoryMap[$id];
                }
            }

            return json_encode($categoriesArray);
        } catch (\Exception $e) {
            $this->_logger->critical('CategoryTree error: ' . $e->getMessage());
            $this->_logger->critical('Stack trace: ' . $e->getTraceAsString());
            return json_encode([]);
        }
    }

    public function getFieldName(): string
    {
        return 'catalog_categories';
    }
}
