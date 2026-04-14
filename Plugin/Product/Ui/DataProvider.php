<?php
/**
 * Add FAQ data to product form data provider
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Plugin\Product\Ui;

use Magento\Catalog\Ui\DataProvider\Product\Form\ProductDataProvider;
use Magento\Framework\App\ResourceConnection;

class DataProvider
{
    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Add FAQ IDs to product data
     *
     * @param ProductDataProvider $subject
     * @param array $result
     * @return array
     */
    public function afterGetData(ProductDataProvider $subject, $result)
    {
        if (!is_array($result)) {
            return $result;
        }

        foreach ($result as $productId => &$productData) {
            if (isset($productData['product'])) {
                $connection = $this->resourceConnection->getConnection();
                $select = $connection->select()
                    ->from($connection->getTableName('panth_faq_item_product'), 'item_id')
                    ->where('product_id = ?', $productId);

                $faqIds = $connection->fetchCol($select);
                $productData['product']['faq_items'] = $faqIds;
            }
        }

        return $result;
    }
}
