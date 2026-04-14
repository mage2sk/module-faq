<?php
/**
 * FAQ Item Edit Controller
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
        $this->logger->info('===== FAQ Item Edit Controller Started =====');
        $id = $this->getRequest()->getParam('item_id');
        $this->logger->info('Item ID from request: ' . ($id ? $id : 'NULL (new item)'));

        if ($id) {
            try {
                $this->logger->info('Attempting to load item ID: ' . $id);
                $model = $this->itemRepository->getById($id);
                $this->logger->info('Item loaded successfully', ['item_id' => $model->getId()]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to load item: ' . $e->getMessage());
                $this->messageManager->addErrorMessage(__('This FAQ item no longer exists.'));
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/');
            }
        } else {
            $this->logger->info('Creating new empty FAQ item model');
            $model = $this->itemFactory->create();
        }

        $this->logger->info('Registering item in registry');
        $this->registry->register('panth_faq_item', $model);

        $this->logger->info('Creating result page');
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Panth_Faq::item');
        $resultPage->getConfig()->getTitle()->prepend($id ? __('Edit FAQ Item') : __('New FAQ Item'));

        $this->logger->info('===== FAQ Item Edit Controller Completed =====');
        return $resultPage;
    }
}
