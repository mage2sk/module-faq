<?php
/**
 * FAQ Item Save Controller
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
use Panth\Faq\Model\ItemFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;

class Save extends Action
{
    const ADMIN_RESOURCE = 'Panth_Faq::item_save';

    /**
     * @var ItemRepositoryInterface
     */
    protected $itemRepository;

    /**
     * @var ItemFactory
     */
    protected $itemFactory;

    /**
     * @var DataPersistorInterface
     */
    protected $dataPersistor;

    /**
     * @param Context $context
     * @param ItemRepositoryInterface $itemRepository
     * @param ItemFactory $itemFactory
     * @param DataPersistorInterface $dataPersistor
     */
    public function __construct(
        Context $context,
        ItemRepositoryInterface $itemRepository,
        ItemFactory $itemFactory,
        DataPersistorInterface $dataPersistor
    ) {
        parent::__construct($context);
        $this->itemRepository = $itemRepository;
        $this->itemFactory = $itemFactory;
        $this->dataPersistor = $dataPersistor;
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();

        if ($data) {
            $id = $this->getRequest()->getParam('item_id');

            try {
                if ($id) {
                    $model = $this->itemRepository->getById($id);
                } else {
                    $model = $this->itemFactory->create();
                }

                $model->setData($data);
                
                // Handle store IDs
                if (isset($data['store_id'])) {
                    $model->setStores($data['store_id']);
                }

                // Handle product IDs
                if (isset($data['products'])) {
                    $products = $data['products'];
                    if (is_string($products)) {
                        $products = json_decode($products, true);
                    }
                    $model->setProducts($products);
                }

                // Handle catalog category IDs
                if (isset($data['catalog_categories'])) {
                    $categories = $data['catalog_categories'];
                    if (is_string($categories)) {
                        $categories = json_decode($categories, true);
                    }
                    $model->setCatalogCategories($categories);
                }

                // Handle page IDs
                if (isset($data['pages'])) {
                    $pages = $data['pages'];
                    if (is_string($pages)) {
                        $pages = json_decode($pages, true);
                    }
                    $model->setPages($pages);
                }

                $this->itemRepository->save($model);
                $this->messageManager->addSuccessMessage(__('The FAQ item has been saved.'));
                $this->dataPersistor->clear('panth_faq_item');

                if ($this->getRequest()->getParam('back')) {
                    return $resultRedirect->setPath('*/*/edit', ['item_id' => $model->getId()]);
                }
                return $resultRedirect->setPath('*/*/');
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the FAQ item.'));
            }

            $this->dataPersistor->set('panth_faq_item', $data);
            return $resultRedirect->setPath('*/*/edit', ['item_id' => $id]);
        }

        return $resultRedirect->setPath('*/*/');
    }
}
