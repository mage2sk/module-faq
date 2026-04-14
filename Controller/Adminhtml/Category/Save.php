<?php
declare(strict_types=1);
namespace Panth\Faq\Controller\Adminhtml\Category;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Panth\Faq\Api\CategoryRepositoryInterface;
use Panth\Faq\Model\CategoryFactory;
use Magento\Framework\App\Request\DataPersistorInterface;

class Save extends Action
{
    const ADMIN_RESOURCE = 'Panth_Faq::category_save';
    protected $categoryRepository;
    protected $categoryFactory;
    protected $dataPersistor;

    public function __construct(Context $context, CategoryRepositoryInterface $categoryRepository, CategoryFactory $categoryFactory, DataPersistorInterface $dataPersistor)
    {
        parent::__construct($context);
        $this->categoryRepository = $categoryRepository;
        $this->categoryFactory = $categoryFactory;
        $this->dataPersistor = $dataPersistor;
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();

        if ($data) {
            $id = $this->getRequest()->getParam('category_id');
            try {
                $model = $id ? $this->categoryRepository->getById($id) : $this->categoryFactory->create();
                $model->setData($data);
                if (isset($data['store_id'])) {
                    $model->setStores($data['store_id']);
                }
                $this->categoryRepository->save($model);
                $this->messageManager->addSuccessMessage(__('The FAQ category has been saved.'));
                $this->dataPersistor->clear('panth_faq_category');
                if ($this->getRequest()->getParam('back')) {
                    return $resultRedirect->setPath('*/*/edit', ['category_id' => $model->getId()]);
                }
                return $resultRedirect->setPath('*/*/');
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the FAQ category.'));
            }
            $this->dataPersistor->set('panth_faq_category', $data);
            return $resultRedirect->setPath('*/*/edit', ['category_id' => $id]);
        }
        return $resultRedirect->setPath('*/*/');
    }
}
