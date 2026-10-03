<?php
declare(strict_types=1);

namespace Panth\Faq\Controller\Adminhtml\Item;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Panth\Faq\Api\ItemRepositoryInterface;
use Magento\Framework\Registry;
use Panth\Faq\Logger\Logger;
use Panth\Faq\Model\ItemFactory;

class Edit extends Action
{
    const ADMIN_RESOURCE = 'Panth_Faq::item_save';

    protected $resultPageFactory;
    protected $itemRepository;
    protected $registry;
    protected $logger;
    protected $itemFactory;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        ItemRepositoryInterface $itemRepository,
        Registry $registry,
        Logger $logger,
        ItemFactory $itemFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->itemRepository = $itemRepository;
        $this->registry = $registry;
        $this->logger = $logger;
        $this->itemFactory = $itemFactory;
    }

    public function execute()
    {
        $id = $this->getRequest()->getParam('item_id');

        if ($id) {
            try {
                $model = $this->itemRepository->getById($id);
            } catch (\Exception $e) {
                $this->logger->error('Failed to load item: ' . $e->getMessage());
                $this->messageManager->addErrorMessage(__('This FAQ item no longer exists.'));
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/');
            }
        } else {
            $model = $this->itemFactory->create();
        }

        $this->registry->register('panth_faq_item', $model);

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Panth_Faq::item');
        $resultPage->getConfig()->getTitle()->prepend($id ? __('Edit FAQ Item') : __('New FAQ Item'));

        return $resultPage;
    }
}
