<?php
declare(strict_types=1);

namespace Panth\Faq\Controller\Adminhtml\Item;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\LayoutFactory;

class ProductsGrid extends Action
{
    const ADMIN_RESOURCE = 'Panth_Faq::item_save';

    protected $resultLayoutFactory;

    public function __construct(
        Context $context,
        LayoutFactory $resultLayoutFactory
    ) {
        parent::__construct($context);
        $this->resultLayoutFactory = $resultLayoutFactory;
    }

    public function execute()
    {
        $resultLayout = $this->resultLayoutFactory->create();
        $resultLayout->getLayout()->getBlock('faq.item.edit.tab.products')->setProducts($this->getRequest()->getPost('products', null));
        return $resultLayout;
    }
}
