<?php
declare(strict_types=1);
namespace Panth\Faq\Controller\Adminhtml\Category;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Panth\Faq\Api\CategoryRepositoryInterface;
use Magento\Framework\Registry;
use Panth\Faq\Logger\Logger;

class Edit extends Action
{
    const ADMIN_RESOURCE = 'Panth_Faq::category_save';
    protected $resultPageFactory;
    protected $categoryRepository;
    protected $registry;
    protected $logger;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        CategoryRepositoryInterface $categoryRepository,
        Registry $registry,
        Logger $logger
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->categoryRepository = $categoryRepository;
        $this->registry = $registry;
        $this->logger = $logger;
    }

    public function execute()
    {
        $this->logger->info('===== FAQ Category Edit Controller Started =====');
        $id = $this->getRequest()->getParam('category_id');
        $this->logger->info('Category ID from request: ' . ($id ? $id : 'NULL (new category)'));

        $model = null;

        if ($id) {
            try {
                $this->logger->info('Attempting to load category ID: ' . $id);
                $model = $this->categoryRepository->getById($id);
                $this->logger->info('Category loaded successfully', ['category_id' => $model->getId()]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to load category: ' . $e->getMessage());
                $this->messageManager->addErrorMessage(__('This FAQ category no longer exists.'));
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/');
            }
        } else {
            $this->logger->info('Creating new FAQ category (no ID provided)');
        }

        $this->logger->info('Registering category in registry');
        $this->registry->register('panth_faq_category', $model);

        $this->logger->info('Creating result page');
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Panth_Faq::category');
        $resultPage->getConfig()->getTitle()->prepend($id ? __('Edit FAQ Category') : __('New FAQ Category'));

        $this->logger->info('===== FAQ Category Edit Controller Completed =====');
        return $resultPage;
    }
}
