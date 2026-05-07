<?php
/**
 * FAQ Router
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Controller;

use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\RouterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Action\Forward;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Faq\Helper\Data as FaqHelper;
use Panth\Faq\Model\ResourceModel\Category as CategoryResource;
use Panth\Faq\Model\ResourceModel\Item as ItemResource;
use Psr\Log\LoggerInterface;

class Router implements RouterInterface
{
    /**
     * @var ActionFactory
     */
    protected $actionFactory;

    /**
     * @var ResponseInterface
     */
    protected $response;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var FaqHelper
     */
    protected $faqHelper;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ItemResource
     */
    protected $itemResource;

    /**
     * @var CategoryResource
     */
    protected $categoryResource;

    public function __construct(
        ActionFactory $actionFactory,
        ResponseInterface $response,
        ScopeConfigInterface $scopeConfig,
        FaqHelper $faqHelper,
        LoggerInterface $logger,
        ResourceConnection $resourceConnection,
        StoreManagerInterface $storeManager,
        ItemResource $itemResource,
        CategoryResource $categoryResource
    ) {
        $this->actionFactory = $actionFactory;
        $this->response = $response;
        $this->scopeConfig = $scopeConfig;
        $this->faqHelper = $faqHelper;
        $this->logger = $logger;
        $this->resourceConnection = $resourceConnection;
        $this->storeManager = $storeManager;
        $this->itemResource = $itemResource;
        $this->categoryResource = $categoryResource;
    }

    /**
     * Match application action by request
     *
     * @param RequestInterface $request
     * @return \Magento\Framework\App\ActionInterface|null
     */
    public function match(RequestInterface $request)
    {
        // Skip if already dispatched to avoid infinite loop
        if ($request->isDispatched()) {
            return null;
        }

        $identifier = trim($request->getPathInfo(), '/');

        // Check if FAQ is enabled
        if (!$this->faqHelper->isEnabled()) {
            return null;
        }

        // Get configured FAQ URL key (default: 'faq'). The admin form
        // stores this under `panth_faq/general/faq_route` (see
        // etc/adminhtml/system.xml + Helper\Data::XML_PATH_FAQ_ROUTE).
        // v1.0.2 and earlier read `panth_faq/general/url_key` here,
        // which is never written, so the admin's chosen slug had no
        // effect and `/faq` was the only working path even when the
        // merchant had configured `/faqs` / `/help` / etc.
        $faqUrlKey = $this->scopeConfig->getValue(
            'panth_faq/general/faq_route',
            ScopeInterface::SCOPE_STORE
        );

        if (!$faqUrlKey) {
            $faqUrlKey = 'faq';
        }

        // Remove any trailing slashes and normalize
        $faqUrlKey = trim($faqUrlKey, '/');

        // Match main FAQ page
        if ($identifier === $faqUrlKey || $identifier === $faqUrlKey . '/index') {
            $request->setModuleName('faq')
                ->setControllerName('index')
                ->setActionName('index')
                ->setPathInfo('/faq/index/index');

            return $this->actionFactory->create(
                Forward::class,
                ['request' => $request]
            );
        }

        // Match FAQ item pages: {configured_url}/item/{url_key}
        if (preg_match('#^' . preg_quote($faqUrlKey, '#') . '/item/([a-z0-9-]+)$#', $identifier, $matches)) {
            $urlKey = $matches[1];
            $itemId = $this->getItemIdByUrlKey($urlKey);
            if ($itemId) {
                $request->setModuleName('faq')
                    ->setControllerName('index')
                    ->setActionName('view')
                    ->setParam('id', $itemId)
                    ->setPathInfo('/faq/index/view/id/' . $itemId);
                return $this->actionFactory->create(Forward::class, ['request' => $request]);
            }
            // The /faq/item/<slug> shape matched but no item exists on the
            // current store (or its is_active override = 0). Force a clean
            // 404 instead of returning null, which would leak the URL to
            // whatever fallback router answers next (admin login on some
            // setups, generic noroute on others).
            return $this->forwardToNoroute($request);
        }

        // Match FAQ category pages: {configured_url}/category/{url_key}
        if (preg_match('#^' . preg_quote($faqUrlKey, '#') . '/category/([a-z0-9-]+)$#', $identifier, $matches)) {
            $urlKey = $matches[1];
            $categoryId = $this->getCategoryIdByUrlKey($urlKey);
            if ($categoryId) {
                $request->setModuleName('faq')
                    ->setControllerName('category')
                    ->setActionName('view')
                    ->setParam('id', $categoryId)
                    ->setPathInfo('/faq/category/view/id/' . $categoryId);
                return $this->actionFactory->create(Forward::class, ['request' => $request]);
            }
            return $this->forwardToNoroute($request);
        }

        return null;
    }

    /**
     * Forward to Magento's standard cms/noroute handler so the response
     * carries a real 404 status code regardless of which other routers
     * might otherwise have caught the URL.
     */
    protected function forwardToNoroute(RequestInterface $request)
    {
        $request->setModuleName('cms')
            ->setControllerName('noroute')
            ->setActionName('index')
            ->setPathInfo('/cms/noroute/index');
        return $this->actionFactory->create(Forward::class, ['request' => $request]);
    }

    /**
     * Get FAQ item ID by URL key — store-scope aware (1.1.0+).
     *
     * Looks up an override row whose url_key matches for the current store
     * first; falls back to the main row's url_key if no per-store override
     * exists for this slug. Honours per-store is_active overrides.
     */
    protected function getItemIdByUrlKey(string $urlKey): ?int
    {
        try {
            $storeId = (int)$this->storeManager->getStore()->getId();
            return $this->itemResource->getItemIdByUrlKeyForStore($urlKey, $storeId);
        } catch (\Throwable $e) {
            $this->logger->error('Error loading FAQ item by URL key: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get FAQ category ID by URL key — store-scope aware (1.1.0+).
     */
    protected function getCategoryIdByUrlKey(string $urlKey): ?int
    {
        try {
            $storeId = (int)$this->storeManager->getStore()->getId();
            return $this->categoryResource->getCategoryIdByUrlKeyForStore($urlKey, $storeId);
        } catch (\Throwable $e) {
            $this->logger->error('Error loading FAQ category by URL key: ' . $e->getMessage());
            return null;
        }
    }
}
