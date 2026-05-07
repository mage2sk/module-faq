<?php
/**
 * Mass Status Change Action
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

class MassStatus extends Action
{
    /**
     * Use the existing item save ACL — this action mutates is_active.
     */
    public const ADMIN_RESOURCE = 'Panth_Faq::item_save';

    /**
     * @var Filter
     */
    protected $filter;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var ItemRepositoryInterface
     */
    protected $itemRepository;

    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        ItemRepositoryInterface $itemRepository
    ) {
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->itemRepository = $itemRepository;
        parent::__construct($context);
    }

    /**
     * @return \Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $type = (string)$this->getRequest()->getParam('type');

        if (!in_array($type, ['enable', 'disable'], true)) {
            $this->messageManager->addErrorMessage(
                __('Invalid status action. Expected "enable" or "disable".')
            );
            return $resultRedirect->setPath('*/*/');
        }
        $newStatus = $type === 'enable' ? 1 : 0;

        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $resultRedirect->setPath('*/*/');
        }

        // Reload each item through the repository so we operate on a clean,
        // fully-hydrated model (collection rows don't trigger _afterLoad and
        // therefore don't have their store/relation/override data populated;
        // saving them directly would risk wiping links). The repository call
        // gives us a model loaded the same way the Edit form would.
        $ok = 0;
        $failed = 0;
        foreach ($collection->getAllIds() as $id) {
            try {
                $item = $this->itemRepository->getById((int)$id);
                $item->setIsActive($newStatus);
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
                __(
                    'A total of %1 FAQ item(s) have been %2.',
                    $ok,
                    $newStatus ? __('enabled') : __('disabled')
                )
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
