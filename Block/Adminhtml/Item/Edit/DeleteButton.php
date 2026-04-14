<?php
declare(strict_types=1);
namespace Panth\Faq\Block\Adminhtml\Item\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DeleteButton extends GenericButton implements ButtonProviderInterface
{
    public function getButtonData()
    {
        if (!$this->getItemId()) {
            return [];
        }
        return [
            'label' => __('Delete'),
            'class' => 'delete',
            'on_click' => 'deleteConfirm(\'' . __(
                'Are you sure you want to delete this FAQ item?'
            ) . '\', \'' . $this->getUrl('*/*/delete', ['item_id' => $this->getItemId()]) . '\')',
            'sort_order' => 20,
        ];
    }
}
