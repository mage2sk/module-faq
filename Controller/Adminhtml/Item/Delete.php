<?php
/**
 * FAQ Item Delete Controller
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Controller\Adminhtml\Item;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Panth\Faq\Api\ItemRepositoryInterface;

class Delete extends Action
{
    const ADMIN_RESOURCE = 'Panth_Faq::item_delete';

    /**
     * @var ItemRepositoryInterface
     */
    protected $itemRepository;

    /**
     * @param Context $context
     * @param ItemRepositoryInterface $itemRepository
     */
    public function __construct(
        Context $context,
        ItemRepositoryInterface $itemRepository
    ) {
        parent::__construct($context);
        $this->itemRepository = $itemRepository;
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $id = $this->getRequest()->getParam('item_id');

        if ($id) {
            try {
                $this->itemRepository->deleteById($id);
                $this->messageManager->addSuccessMessage(__('The FAQ item has been deleted.'));
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        }

        return $resultRedirect->setPath('*/*/');
    }
}
