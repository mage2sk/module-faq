<?php
declare(strict_types=1);

namespace Panth\Faq\Controller\Adminhtml\Item;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Ui\Component\MassAction\Filter;
use Panth\Faq\Model\ResourceModel\Item\CollectionFactory;
use Panth\Faq\Api\ItemRepositoryInterface;

class MassDelete extends Action
{
    const ADMIN_RESOURCE = 'Panth_Faq::item_delete';

    protected $filter;

    protected $collectionFactory;

    protected $itemRepository;

    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        ItemRepositoryInterface $itemRepository
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->itemRepository = $itemRepository;
    }

    public function execute()
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $collectionSize = $collection->getSize();

        foreach ($collection as $item) {
            $this->itemRepository->delete($item);
        }

        $this->messageManager->addSuccessMessage(__('A total of %1 record(s) have been deleted.', $collectionSize));

        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('*/*/');
    }
}
