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
        $id = $this->getRequest()->getParam('category_id');

        $model = null;

        if ($id) {
            try {
                $model = $this->categoryRepository->getById($id);
            } catch (\Exception $e) {
                $this->logger->error('Failed to load category: ' . $e->getMessage());
                $this->messageManager->addErrorMessage(__('This FAQ category no longer exists.'));
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/');
            }
        } else {
        }

        $this->registry->register('panth_faq_category', $model);

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Panth_Faq::category');
        $resultPage->getConfig()->getTitle()->prepend($id ? __('Edit FAQ Category') : __('New FAQ Category'));

        return $resultPage;
    }
}
