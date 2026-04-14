<?php
/**
 * FAQ Item CMS Page Assignment Block
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
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory;
use Magento\Framework\Registry;

class Page extends Extended
{
    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var CollectionFactory
     */
    protected $pageCollectionFactory;

    /**
     * @param Context $context
     * @param BackendHelper $backendHelper
     * @param CollectionFactory $pageCollectionFactory
     * @param Registry $registry
     * @param array $data
     */
    public function __construct(
        Context $context,
        BackendHelper $backendHelper,
        CollectionFactory $pageCollectionFactory,
        Registry $registry,
        array $data = []
    ) {
        $this->pageCollectionFactory = $pageCollectionFactory;
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
        $this->setId('faq_item_pages');
        $this->setDefaultSort('page_id');
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
        $collection = $this->pageCollectionFactory->create();
        $collection->addFieldToFilter('is_active', 1);

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
            $logger->info('Page Grid - Item type: ' . gettype($item));
            $logger->info('Page Grid - Item class: ' . ($item ? get_class($item) : 'null'));
            $logger->info('Page Grid - Item ID: ' . ($item && $item->getId() ? $item->getId() : 'null'));

            if ($item && $item->getId()) {
                $values = $this->getSelectedPages();
                $logger->info('Page Grid - Values type after getSelectedPages: ' . gettype($values));
                $logger->info('Page Grid - Values content: ' . print_r($values, true));
            }
        } catch (\Exception $e) {
            // Fail safe - use empty array
            $logger->error('Page Grid - Exception: ' . $e->getMessage());
            $values = [];
        }

        // Ensure values is definitely an array
        if (!is_array($values)) {
            $logger->error('Page Grid - VALUES IS NOT AN ARRAY! Type: ' . gettype($values) . ' Value: ' . print_r($values, true));
            $values = [];
        }

        $logger->info('Page Grid - Final values type before addColumn: ' . gettype($values));

        $this->addColumn(
            'in_pages',
            [
                'type' => 'checkbox',
                'name' => 'in_pages',
                'values' => $values,
                'index' => 'page_id',
                'header_css_class' => 'col-select col-massaction',
                'column_css_class' => 'col-select col-massaction'
            ]
        );

        $this->addColumn(
            'page_id',
            [
                'header' => __('ID'),
                'sortable' => true,
                'index' => 'page_id',
                'header_css_class' => 'col-id',
                'column_css_class' => 'col-id'
            ]
        );

        $this->addColumn(
            'title',
            [
                'header' => __('Title'),
                'index' => 'title'
            ]
        );

        $this->addColumn(
            'identifier',
            [
                'header' => __('URL Key'),
                'index' => 'identifier'
            ]
        );

        $this->addColumn(
            'is_active',
            [
                'header' => __('Status'),
                'index' => 'is_active',
                'type' => 'options',
                'options' => [
                    0 => __('Disabled'),
                    1 => __('Enabled')
                ]
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
        return $this->getUrl('faq/item/pagesgrid', ['_current' => true]);
    }

    /**
     * Get selected pages
     *
     * @return array
     */
    protected function getSelectedPages(): array
    {
        try {
            $pages = $this->getRequest()->getPost('pages');
            if ($pages !== null) {
                // Post data exists, use it
                if (!is_array($pages)) {
                    $pages = [];
                }
                return array_filter(array_map('intval', (array)$pages));
            }

            $item = $this->getItem();
            // Only try to get data if item exists and has an ID
            if (!$item || !$item->getId()) {
                return [];
            }

            $pages = $item->getData('pages');

            // If pages is null or empty, return empty array
            if ($pages === null || $pages === '') {
                return [];
            }

            // Handle JSON or serialized data
            if (is_string($pages)) {
                $decoded = json_decode($pages, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return array_filter(array_map('intval', $decoded));
                }
                $pages = array_filter(explode(',', $pages));
            }

            if (!is_array($pages)) {
                return [];
            }

            // Ensure we return array of integers only
            return array_filter(array_map('intval', $pages));
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
        $scriptBlock->setTemplate('Panth_Faq::item/edit/tab/page.phtml');
        $scriptBlock->setGridId($this->getId());

        return $html . $scriptBlock->toHtml();
    }
}
