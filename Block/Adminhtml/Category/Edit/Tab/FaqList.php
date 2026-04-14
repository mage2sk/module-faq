<?php
/**
 * Category FAQ List Block
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Block\Adminhtml\Category\Edit\Tab;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Panth\Faq\Model\ResourceModel\Item\CollectionFactory;
use Magento\Framework\App\ResourceConnection;

class FaqList extends Template
{
    /**
     * @var string
     */
    protected $_template = 'Panth_Faq::category/faq_assignment.phtml';

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var CollectionFactory
     */
    protected $faqCollectionFactory;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param CollectionFactory $faqCollectionFactory
     * @param ResourceConnection $resourceConnection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        CollectionFactory $faqCollectionFactory,
        ResourceConnection $resourceConnection,
        array $data = []
    ) {
        $this->registry = $registry;
        $this->faqCollectionFactory = $faqCollectionFactory;
        $this->resourceConnection = $resourceConnection;
        parent::__construct($context, $data);
    }

    /**
     * Get current category
     *
     * @return \Magento\Catalog\Model\Category|null
     */
    public function getCategory()
    {
        return $this->registry->registry('current_category');
    }

    /**
     * Get FAQ items
     *
     * @return \Panth\Faq\Model\Item[]
     */
    public function getFaqItems()
    {
        $collection = $this->faqCollectionFactory->create();
        $collection->addFieldToFilter('is_active', 1)
            ->setOrder('question', 'ASC')
            ->setPageSize(100);

        return $collection->getItems();
    }

    /**
     * Get selected FAQ IDs
     *
     * @return array
     */
    public function getSelectedFaqIds(): array
    {
        $category = $this->getCategory();
        if (!$category || !$category->getId()) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('panth_faq_item_catalog_category');

        $select = $connection->select()
            ->from($tableName, 'item_id')
            ->where('category_id = ?', $category->getId());

        $faqIds = $connection->fetchCol($select);
        return array_map('intval', $faqIds);
    }
}
