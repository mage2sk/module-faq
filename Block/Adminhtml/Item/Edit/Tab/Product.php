<?php
/**
 * FAQ Item Product Assignment Block
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Block\Adminhtml\Item\Edit\Tab;

use Magento\Backend\Block\Widget\Grid\Extended;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Helper\Data as BackendHelper;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Registry;

class Product extends Extended
{
    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var CollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * @param Context $context
     * @param BackendHelper $backendHelper
     * @param CollectionFactory $productCollectionFactory
     * @param Registry $registry
     * @param array $data
     */
    public function __construct(
        Context $context,
        BackendHelper $backendHelper,
        CollectionFactory $productCollectionFactory,
        Registry $registry,
        array $data = []
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->registry = $registry;
        parent::__construct($context, $backendHelper, $data);
    }

    /**
     * Initialize grid
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setId('faq_item_products');
        $this->setDefaultSort('entity_id');
        $this->setDefaultDir('DESC');
        $this->setUseAjax(true);
        $this->setSaveParametersInSession(true);
    }

    /**
     * Get current FAQ item
     *
     * @return \Panth\Faq\Model\Item|null
     */
    public function getItem()
    {
        return $this->registry->registry('panth_faq_item');
    }

    /**
     * Prepare collection
     *
     * @return $this
     */
    protected function _prepareCollection()
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect('name');
        $collection->addAttributeToSelect('sku');
        $collection->addAttributeToSelect('price');

        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    /**
     * Prepare columns
     *
     * @return $this
     */
    protected function _prepareColumns()
    {
        // Always ensure values is an array - even if empty
        $values = [];

        // DEBUG: Log the type and value
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/faq_grid_debug.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);

        try {
            $item = $this->getItem();
            $logger->info('Product Grid - Item type: ' . gettype($item));
            $logger->info('Product Grid - Item class: ' . ($item ? get_class($item) : 'null'));
            $logger->info('Product Grid - Item ID: ' . ($item && $item->getId() ? $item->getId() : 'null'));

            if ($item && $item->getId()) {
                $values = $this->getSelectedProducts();
                $logger->info('Product Grid - Values type after getSelectedProducts: ' . gettype($values));
                $logger->info('Product Grid - Values content: ' . print_r($values, true));
            }
        } catch (\Exception $e) {
            // Fail safe - use empty array
            $logger->error('Product Grid - Exception: ' . $e->getMessage());
            $values = [];
        }

        // Ensure values is definitely an array
        if (!is_array($values)) {
            $logger->error('Product Grid - VALUES IS NOT AN ARRAY! Type: ' . gettype($values) . ' Value: ' . print_r($values, true));
            $values = [];
        }

        $logger->info('Product Grid - Final values type before addColumn: ' . gettype($values));

        $this->addColumn(
            'in_products',
            [
                'type' => 'checkbox',
                'name' => 'in_products',
                'values' => $values,
                'index' => 'entity_id',
                'header_css_class' => 'col-select col-massaction',
                'column_css_class' => 'col-select col-massaction'
            ]
        );

        $this->addColumn(
            'entity_id',
            [
                'header' => __('ID'),
                'sortable' => true,
                'index' => 'entity_id',
                'header_css_class' => 'col-id',
                'column_css_class' => 'col-id'
            ]
        );

        $this->addColumn(
            'name',
            [
                'header' => __('Name'),
                'index' => 'name'
            ]
        );

        $this->addColumn(
            'sku',
            [
                'header' => __('SKU'),
                'index' => 'sku'
            ]
        );

        $this->addColumn(
            'price',
            [
                'header' => __('Price'),
                'type' => 'currency',
                'currency_code' => (string)$this->_scopeConfig->getValue(
                    \Magento\Directory\Model\Currency::XML_PATH_CURRENCY_BASE,
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                ),
                'index' => 'price'
            ]
        );

        return parent::_prepareColumns();
    }

    /**
     * Get grid url
     *
     * @return string
     */
    public function getGridUrl()
    {
        return $this->getUrl('faq/item/productsgrid', ['_current' => true]);
    }

    /**
     * Get selected products
     *
     * @return array
     */
    protected function getSelectedProducts(): array
    {
        try {
            $products = $this->getRequest()->getPost('products');
            if ($products !== null) {
                // Post data exists, use it
                if (!is_array($products)) {
                    $products = [];
                }
                return array_filter(array_map('intval', (array)$products));
            }

            $item = $this->getItem();
            // Only try to get data if item exists and has an ID
            if (!$item || !$item->getId()) {
                return [];
            }

            $products = $item->getData('products');

            // If products is null or empty, return empty array
            if ($products === null || $products === '') {
                return [];
            }

            // Handle JSON or serialized data
            if (is_string($products)) {
                $decoded = json_decode($products, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return array_filter(array_map('intval', $decoded));
                }
                $products = array_filter(explode(',', $products));
            }

            if (!is_array($products)) {
                return [];
            }

            // Ensure we return array of integers only
            return array_filter(array_map('intval', $products));
        } catch (\Exception $e) {
            // Fail safe - always return array
            return [];
        }
    }

    /**
     * Can show tab
     *
     * @return bool
     */
    public function canShowTab()
    {
        return true;
    }

    /**
     * Is hidden
     *
     * @return bool
     */
    public function isHidden()
    {
        return false;
    }

    /**
     * After HTML
     *
     * @return string
     */
    public function _afterToHtml($html)
    {
        $html = parent::_afterToHtml($html);

        $scriptBlock = $this->getLayout()->createBlock(\Magento\Backend\Block\Template::class);
        $scriptBlock->setTemplate('Panth_Faq::item/edit/tab/product.phtml');
        $scriptBlock->setGridId($this->getId());

        return $html . $scriptBlock->toHtml();
    }
}
