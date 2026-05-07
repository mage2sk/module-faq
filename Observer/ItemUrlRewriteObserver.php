<?php
/**
 * FAQ Item URL Rewrite Observer
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
use Magento\UrlRewrite\Model\UrlRewriteFactory;
use Magento\UrlRewrite\Model\ResourceModel\UrlRewriteCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Panth\Faq\Model\ResourceModel\Item as ItemResource;
use Psr\Log\LoggerInterface;

class ItemUrlRewriteObserver implements ObserverInterface
{
    const ENTITY_TYPE = 'faq_item';

    /**
     * @var UrlRewriteFactory
     */
    protected $urlRewriteFactory;

    /**
     * @var UrlRewriteCollectionFactory
     */
    protected $urlRewriteCollectionFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param UrlRewriteFactory $urlRewriteFactory
     * @param UrlRewriteCollectionFactory $urlRewriteCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     */
    /**
     * @var ItemResource
     */
    protected $itemResource;

    public function __construct(
        UrlRewriteFactory $urlRewriteFactory,
        UrlRewriteCollectionFactory $urlRewriteCollectionFactory,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        ItemResource $itemResource
    ) {
        $this->urlRewriteFactory = $urlRewriteFactory;
        $this->urlRewriteCollectionFactory = $urlRewriteCollectionFactory;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->itemResource = $itemResource;
    }

    /**
     * Generate URL rewrites for FAQ item
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        try {
            $item = $observer->getEvent()->getItem();

            if (!$item || !$item->getId() || !$item->getUrlKey()) {
                return;
            }

            $itemId = (int)$item->getId();

            // Always read the default url_key from the main table — the
            // model's getUrlKey() may carry a scoped value when this
            // observer fires off a per-store save.
            $defaults = $this->itemResource->loadDefaultValuesPublic($itemId);
            $defaultUrlKey = (string)($defaults['url_key'] ?? $item->getUrlKey());
            if ($defaultUrlKey === '') {
                return;
            }

            $this->deleteExistingRewrites($itemId);

            // Determine target stores. The item may be assigned to one or
            // more store_ids via the membership multiselect; if nothing is
            // assigned we fall through to the admin scope (0).
            $stores = $item->getStores() ?: $item->getData('store_id') ?: [0];
            if (!is_array($stores)) {
                $stores = [$stores];
            }

            // For each target store, write the rewrite using the per-store
            // url_key override when one exists; fall back to the default.
            foreach ($stores as $storeId) {
                $storeId = (int)$storeId;
                $slug = $defaultUrlKey;
                if ($storeId > 0) {
                    $override = $this->itemResource->getStoreOverrideRow($itemId, $storeId);
                    if (!empty($override['url_key'])) {
                        $slug = (string)$override['url_key'];
                    }
                }
                $this->createUrlRewrite($itemId, $slug, $storeId);
            }
        } catch (\Throwable $e) {
            $this->logger->error('FAQ Item URL Rewrite Error: ' . $e->getMessage());
        }
    }

    /**
     * Delete existing URL rewrites
     *
     * @param int $itemId
     * @return void
     */
    protected function deleteExistingRewrites($itemId)
    {
        $collection = $this->urlRewriteCollectionFactory->create();
        $collection->addFieldToFilter('entity_type', self::ENTITY_TYPE)
            ->addFieldToFilter('entity_id', $itemId);

        foreach ($collection as $urlRewrite) {
            try {
                $urlRewrite->delete();
            } catch (\Exception $e) {
                $this->logger->error('Error deleting URL rewrite: ' . $e->getMessage());
            }
        }
    }

    /**
     * Create URL rewrite for one (item, slug, storeId) tuple.
     */
    protected function createUrlRewrite(int $itemId, string $slug, int $storeId): void
    {
        $faqUrlKey = $this->scopeConfig->getValue(
            'panth_faq/general/faq_route',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if (!$faqUrlKey) {
            $faqUrlKey = 'faq';
        }
        $faqUrlKey = trim((string)$faqUrlKey, '/');

        $urlRewrite = $this->urlRewriteFactory->create();
        $urlRewrite->setEntityType(self::ENTITY_TYPE)
            ->setEntityId($itemId)
            ->setRequestPath($faqUrlKey . '/item/' . $slug)
            ->setTargetPath('faq/index/view/id/' . $itemId)
            ->setStoreId($storeId)
            ->setIsAutogenerated(1);

        try {
            $urlRewrite->save();
        } catch (\Throwable $e) {
            $this->logger->error('Error creating URL rewrite: ' . $e->getMessage());
        }
    }
}
