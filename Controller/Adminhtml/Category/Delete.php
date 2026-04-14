<?php
declare(strict_types=1);
namespace Panth\Faq\Controller\Adminhtml\Category;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Panth\Faq\Api\CategoryRepositoryInterface;

class Delete extends Action
{
    const ADMIN_RESOURCE = 'Panth_Faq::category_delete';
    protected $categoryRepository;

    public function __construct(Context $context, CategoryRepositoryInterface $categoryRepository)
    {
        parent::__construct($context);
        $this->categoryRepository = $categoryRepository;
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $id = $this->getRequest()->getParam('category_id');
        if ($id) {
            try {
                $this->categoryRepository->deleteById($id);
                $this->messageManager->addSuccessMessage(__('The FAQ category has been deleted.'));
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        }
        return $resultRedirect->setPath('*/*/');
    }
}
