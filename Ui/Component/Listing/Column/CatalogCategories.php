<?php
declare(strict_types=1);

namespace Panth\Faq\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\App\ResourceConnection;

class CatalogCategories extends Column
{
    protected $resourceConnection;

    protected $categoryNames = [];

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        ResourceConnection $resourceConnection,
        array $components = [],
        array $data = []
    ) {
        $this->resourceConnection = $resourceConnection;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                $item[$this->getData('name')] = $this->getCatalogCategoriesHtml($item['item_id']);
            }
        }

        return $dataSource;
    }

    protected function getCatalogCategoriesHtml($itemId)
    {
        $connection = $this->resourceConnection->getConnection();
        $junctionTable = $this->resourceConnection->getTableName('panth_faq_item_catalog_category');
        $categoryTable = $this->resourceConnection->getTableName('catalog_category_entity_varchar');
        $eavAttribute = $this->resourceConnection->getTableName('eav_attribute');

        $select = $connection->select()
            ->from(['jt' => $junctionTable], ['category_id'])
            ->joinLeft(
                ['cev' => $categoryTable],
                'jt.category_id = cev.entity_id AND cev.store_id = 0',
                ['value']
            )
            ->joinLeft(
                ['ea' => $eavAttribute],
                "cev.attribute_id = ea.attribute_id AND ea.attribute_code = 'name'",
                []
            )
            ->where('jt.item_id = ?', $itemId)
            ->where('ea.attribute_code = ?', 'name')
            ->order('cev.value ASC');

        $categories = $connection->fetchAll($select);

        if (empty($categories)) {
            return '<span style="color: #999; font-style: italic;">No catalog categories</span>';
        }

        $html = [];
        foreach ($categories as $category) {
            $categoryName = $category['value'] ?? 'Category #' . $category['category_id'];
            $html[] = sprintf(
                '<span title="Catalog Category: %s (ID: %d)" style="display: inline-block; padding: 3px 8px; margin: 2px; background: #20364d; color: white; border-radius: 3px; font-size: 11px; cursor: help;">%s</span>',
                $this->escapeHtml($categoryName),
                $category['category_id'],
                $this->escapeHtml($this->truncate($categoryName, 20))
            );
        }

        return implode(' ', $html);
    }

    protected function truncate($string, $length)
    {
        if (strlen($string) > $length) {
            return substr($string, 0, $length) . '...';
        }
        return $string;
    }

    protected function escapeHtml($string)
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}
