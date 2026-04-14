<?php
/**
 * FAQ Products Column Renderer
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
use Magento\Framework\App\ResourceConnection;

class Products extends Column
{
    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param ResourceConnection $resourceConnection
     * @param array $components
     * @param array $data
     */
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

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            $connection = $this->resourceConnection->getConnection();

            foreach ($dataSource['data']['items'] as &$item) {
                $itemId = $item['item_id'];

                // Get assigned products
                $select = $connection->select()
                    ->from(
                        ['fp' => $connection->getTableName('panth_faq_item_product')],
                        ['product_id']
                    )
                    ->joinLeft(
                        ['cpe' => $connection->getTableName('catalog_product_entity')],
                        'fp.product_id = cpe.entity_id',
                        ['sku']
                    )
                    ->joinLeft(
                        ['cpev' => $connection->getTableName('catalog_product_entity_varchar')],
                        'cpe.entity_id = cpev.entity_id AND cpev.attribute_id = (SELECT attribute_id FROM ' . $connection->getTableName('eav_attribute') . ' WHERE attribute_code = "name" AND entity_type_id = 4 LIMIT 1) AND cpev.store_id = 0',
                        ['name' => 'value']
                    )
                    ->where('fp.item_id = ?', $itemId)
                    ->limit(5);

                $products = $connection->fetchAll($select);

                if (!empty($products)) {
                    $productLabels = [];
                    foreach ($products as $product) {
                        $name = $product['name'] ?: $product['sku'] ?: 'Product';
                        $productLabels[] = sprintf(
                            '<span title="ID: %d - %s" style="display: inline-block; padding: 3px 8px; margin: 2px; background: #0089f7; color: white; border-radius: 3px; font-size: 11px; cursor: help;">%s</span>',
                            $product['product_id'],
                            $this->escapeHtml($name),
                            $this->escapeHtml($this->truncate($name, 20))
                        );
                    }
                    $item['products'] = implode(' ', $productLabels);

                    if (count($products) >= 5) {
                        $item['products'] .= ' <span style="color: #666; font-size: 11px;">...</span>';
                    }
                } else {
                    $item['products'] = '<span style="color: #999; font-style: italic;">None</span>';
                }
            }
        }

        return $dataSource;
    }

    /**
     * Truncate string
     *
     * @param string $string
     * @param int $length
     * @return string
     */
    protected function truncate($string, $length)
    {
        if (strlen($string) > $length) {
            return substr($string, 0, $length) . '...';
        }
        return $string;
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
