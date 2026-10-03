<?php
declare(strict_types=1);

namespace Panth\Faq\Block\Adminhtml\Item\Edit;

use Magento\Backend\Block\Widget\Context;

abstract class GenericButton
{
    protected $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    public function getItemId()
    {
        return $this->context->getRequest()->getParam('item_id');
    }

    public function getUrl($route = '', $params = [])
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}
