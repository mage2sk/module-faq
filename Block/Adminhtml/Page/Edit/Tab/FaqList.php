<?php
/**
 * CMS Page FAQ List Block
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Block\Adminhtml\Page\Edit\Tab;

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
    protected $_template = 'Panth_Faq::page/faq_assignment.phtml';

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
     * Get current page
     *
     * @return \Magento\Cms\Model\Page|null
     */
    public function getPage()
    {
        return $this->registry->registry('cms_page');
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
        $page = $this->getPage();
        if (!$page || !$page->getId()) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('panth_faq_item_page');

        $select = $connection->select()
            ->from($tableName, 'item_id')
            ->where('page_id = ?', $page->getId());

        $faqIds = $connection->fetchCol($select);
        return array_map('intval', $faqIds);
    }
}
