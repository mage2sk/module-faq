<?php
declare(strict_types=1);
namespace Panth\Faq\Block\Adminhtml\Category\Edit;

use Magento\Backend\Block\Widget\Context;

abstract class GenericButton
{
    protected $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    public function getCategoryId()
    {
        return $this->context->getRequest()->getParam('category_id');
    }

    public function getUrl($route = '', $params = [])
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}
