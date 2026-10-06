<?php
declare(strict_types=1);

namespace Panth\Faq\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Panth\Faq\Helper\Data as FaqHelper;
use Panth\Faq\Model\Config\Source\ProductPosition;

class AddProductFaqPositionHandle implements ObserverInterface
{
    public const HANDLE_PREFIX = 'panth_faq_product_';

    private FaqHelper $helper;

    public function __construct(FaqHelper $helper)
    {
        $this->helper = $helper;
    }

    public function execute(Observer $observer)
    {
        if ($observer->getData('full_action_name') !== 'catalog_product_view') {
            return;
        }
        if (!$this->helper->isProductPageEnabled()) {
            return;
        }
        $position = $this->helper->getProductPosition();
        if ($position === ProductPosition::POSITION_TAB) {
            return;
        }
        $layout = $observer->getData('layout');
        if ($layout) {
            $layout->getUpdate()->addHandle(self::HANDLE_PREFIX . $position);
        }
    }
}
