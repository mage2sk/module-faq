<?php
/**
 * FAQ Category Column Renderer
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Panth\Faq\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;

class Category extends Column
{
    /**
     * @var CategoryCollectionFactory
     */
    protected $categoryCollectionFactory;

    /**
     * @var array
     */
    protected $categoryNames = [];

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        CategoryCollectionFactory $categoryCollectionFactory,
        array $components = [],
        array $data = []
    ) {
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            $this->loadCategoryNames();

            foreach ($dataSource['data']['items'] as &$item) {
                if (isset($item['category_id'])) {
                    $categoryIds = $item['category_id'];

                    // Handle comma-separated IDs
                    if (is_string($categoryIds)) {
                        $categoryIds = explode(',', $categoryIds);
                    } elseif (!is_array($categoryIds)) {
                        $categoryIds = [$categoryIds];
                    }

                    $categoryIds = array_filter($categoryIds);

                    if (!empty($categoryIds)) {
                        $categoryLabels = [];
                        foreach ($categoryIds as $catId) {
                            $catId = (int)$catId;
                            if (isset($this->categoryNames[$catId])) {
                                $categoryLabels[] = sprintf(
                                    '<span title="ID: %d - %s" style="display: inline-block; padding: 3px 8px; margin: 2px; background: #e40020; color: white; border-radius: 3px; font-size: 11px; cursor: help;">%s</span>',
                                    $catId,
                                    $this->escapeHtml($this->categoryNames[$catId]),
                                    $this->escapeHtml($this->categoryNames[$catId])
                                );
                            } else {
                                $categoryLabels[] = sprintf(
                                    '<span title="ID: %d" style="display: inline-block; padding: 3px 8px; margin: 2px; background: #6c757d; color: white; border-radius: 3px; font-size: 11px;">ID: %d</span>',
                                    $catId,
                                    $catId
                                );
                            }
                        }
                        $item['category_id'] = implode(' ', $categoryLabels);
                    } else {
                        $item['category_id'] = '<span style="color: #999; font-style: italic;">No category</span>';
                    }
                } else {
                    $item['category_id'] = '<span style="color: #999; font-style: italic;">No category</span>';
                }
            }
        }

        return $dataSource;
    }

    /**
     * Load category names
     *
     * @return void
     */
    protected function loadCategoryNames()
    {
        if (empty($this->categoryNames)) {
            $collection = $this->categoryCollectionFactory->create();
            foreach ($collection as $category) {
                $this->categoryNames[$category->getId()] = $category->getName();
            }
        }
    }

    /**
     * Escape HTML
     *
     * @param string $string
     * @return string
     */
    protected function escapeHtml($string)
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}
