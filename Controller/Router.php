<?php
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
    protected $actionFactory;

    protected $response;

    protected $scopeConfig;

    protected $faqHelper;

    protected $logger;

    protected $resourceConnection;

    protected $storeManager;

    protected $itemResource;

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

    public function match(RequestInterface $request)
    {
        if ($request->isDispatched()) {
            return null;
        }

        $identifier = trim($request->getPathInfo(), '/');

        if (!$this->faqHelper->isEnabled()) {
            return null;
        }

        $faqUrlKey = $this->scopeConfig->getValue(
            'panth_faq/general/faq_route',
            ScopeInterface::SCOPE_STORE
        );

        if (!$faqUrlKey) {
            $faqUrlKey = 'faq';
        }

        $faqUrlKey = trim($faqUrlKey, '/');

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

            return $this->forwardToNoroute($request);
        }

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

    protected function forwardToNoroute(RequestInterface $request)
    {
        $request->setModuleName('cms')
            ->setControllerName('noroute')
            ->setActionName('index')
            ->setPathInfo('/cms/noroute/index');
        return $this->actionFactory->create(Forward::class, ['request' => $request]);
    }

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
