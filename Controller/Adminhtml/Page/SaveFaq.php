<?php
declare(strict_types=1);

namespace Panth\Faq\Controller\Adminhtml\Page;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\ResourceConnection;

class SaveFaq extends Action
{
    protected $resultJsonFactory;

    protected $resourceConnection;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        ResourceConnection $resourceConnection
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->resourceConnection = $resourceConnection;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            $pageId = (int)$this->getRequest()->getParam('page_id');
            $faqIds = $this->getRequest()->getParam('faq_ids', '');

            if (!$pageId) {
                return $result->setData(['success' => false, 'message' => __('Invalid page ID')]);
            }

            $faqIds = $faqIds ? explode(',', $faqIds) : [];
            $faqIds = array_filter(array_map('intval', $faqIds));

            $connection = $this->resourceConnection->getConnection();
            $tableName = $connection->getTableName('panth_faq_item_page');

            $connection->delete($tableName, ['page_id = ?' => $pageId]);

            if (!empty($faqIds)) {
                $data = [];
                foreach ($faqIds as $faqId) {
                    $data[] = ['item_id' => $faqId, 'page_id' => $pageId];
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

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Panth_Faq::item');
    }
}
