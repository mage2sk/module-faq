<?php
declare(strict_types=1);

namespace Panth\Faq\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Psr\Log\LoggerInterface;

class ConfigSaveAfter implements ObserverInterface
{
    protected $cacheTypeList;

    protected $logger;

    public function __construct(
        TypeListInterface $cacheTypeList,
        LoggerInterface $logger
    ) {
        $this->cacheTypeList = $cacheTypeList;
        $this->logger = $logger;
    }

    public function execute(Observer $observer)
    {
        try {
            $this->cacheTypeList->invalidate('full_page');
            $this->cacheTypeList->invalidate('block_html');
        } catch (\Exception $e) {
            $this->logger->error('Panth_Faq: Error invalidating cache after config save: ' . $e->getMessage());
        }
    }
}
