<?php
/**
 * Mass Hide from Main Page Action
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
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;
use Panth\Faq\Api\ItemRepositoryInterface;
use Panth\Faq\Model\ResourceModel\Item\CollectionFactory;

class MassHideFromMain extends Action
{
    public const ADMIN_RESOURCE = 'Panth_Faq::item_save';

    public function __construct(
        Context $context,
        protected Filter $filter,
        protected CollectionFactory $collectionFactory,
        protected ItemRepositoryInterface $itemRepository
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $resultRedirect->setPath('*/*/');
        }

        $ok = 0;
        $failed = 0;
        foreach ($collection->getAllIds() as $id) {
            try {
                $item = $this->itemRepository->getById((int)$id);
                $item->setShowOnMain(0);
                $this->itemRepository->save($item);
                $ok++;
            } catch (\Throwable $e) {
                $failed++;
                $this->messageManager->addErrorMessage(
                    __('FAQ item %1: %2', $id, $e->getMessage())
                );
            }
        }

        if ($ok > 0) {
            $this->messageManager->addSuccessMessage(
                __('A total of %1 FAQ item(s) have been hidden from main page.', $ok)
            );
        }
        if ($failed > 0 && $ok === 0) {
            $this->messageManager->addWarningMessage(
                __('No FAQ items were updated. See errors above.')
            );
        }

        return $resultRedirect->setPath('*/*/');
    }
}
