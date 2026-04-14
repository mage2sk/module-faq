<?php
/**
 * Save Category FAQ Assignments
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Controller\Adminhtml\Category;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\ResourceConnection;

class SaveFaq extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        ResourceConnection $resourceConnection
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            $categoryId = (int)$this->getRequest()->getParam('category_id');
            $faqIds = $this->getRequest()->getParam('faq_ids', '');

            if (!$categoryId) {
                return $result->setData(['success' => false, 'message' => __('Invalid category ID')]);
            }

            $faqIds = $faqIds ? explode(',', $faqIds) : [];
            $faqIds = array_filter(array_map('intval', $faqIds));

            $connection = $this->resourceConnection->getConnection();
            $tableName = $connection->getTableName('panth_faq_item_catalog_category');

            // Delete existing assignments
            $connection->delete($tableName, ['category_id = ?' => $categoryId]);

            // Insert new assignments
            if (!empty($faqIds)) {
                $data = [];
                foreach ($faqIds as $faqId) {
                    $data[] = ['item_id' => $faqId, 'category_id' => $categoryId];
                }
                $connection->insertMultiple($tableName, $data);
            }

            return $result->setData([
                'success' => true,
                'message' => __('FAQ assignments saved successfully.')
            ]);

        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => __('Error saving FAQ assignments: %1', $e->getMessage())
            ]);
        }
    }

    /**
     * Check ACL
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Panth_Faq::item');
    }
}
