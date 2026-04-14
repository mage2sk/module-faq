<?php
/**
 * FAQ Config Save Observer
 * Handles post-config-save actions (e.g., cache cleanup)
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Psr\Log\LoggerInterface;

class ConfigSaveAfter implements ObserverInterface
{
    /**
     * @var TypeListInterface
     */
    protected $cacheTypeList;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param TypeListInterface $cacheTypeList
     * @param LoggerInterface $logger
     */
    public function __construct(
        TypeListInterface $cacheTypeList,
        LoggerInterface $logger
    ) {
        $this->cacheTypeList = $cacheTypeList;
        $this->logger = $logger;
    }

    /**
     * Execute observer
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        try {
            // Invalidate full page cache and block HTML cache when FAQ config changes
            $this->cacheTypeList->invalidate('full_page');
            $this->cacheTypeList->invalidate('block_html');
        } catch (\Exception $e) {
            $this->logger->error('Panth_Faq: Error invalidating cache after config save: ' . $e->getMessage());
        }
    }
}
