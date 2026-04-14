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
use Psr\Log\LoggerInterface;
use Panth\Faq\Helper\Data as FaqHelper;

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
     * @param ActionFactory $actionFactory
     * @param ResponseInterface $response
     * @param ScopeConfigInterface $scopeConfig
     * @param FaqHelper $faqHelper
     * @param LoggerInterface $logger
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        ActionFactory $actionFactory,
        ResponseInterface $response,
        ScopeConfigInterface $scopeConfig,
        FaqHelper $faqHelper,
        LoggerInterface $logger,
        ResourceConnection $resourceConnection
    ) {
        $this->actionFactory = $actionFactory;
        $this->response = $response;
        $this->scopeConfig = $scopeConfig;
        $this->faqHelper = $faqHelper;
        $this->logger = $logger;
        $this->resourceConnection = $resourceConnection;
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

        // Get configured FAQ URL key (default: 'faq')
        $faqUrlKey = $this->scopeConfig->getValue(
            'panth_faq/general/url_key',
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

            // Load item by URL key and get ID
            $itemId = $this->getItemIdByUrlKey($urlKey);
            if ($itemId) {
                $request->setModuleName('faq')
                    ->setControllerName('index')
                    ->setActionName('view')
                    ->setParam('id', $itemId)
                    ->setPathInfo('/faq/index/view/id/' . $itemId);

                return $this->actionFactory->create(
                    Forward::class,
                    ['request' => $request]
                );
            }
        }

        // Match FAQ category pages: {configured_url}/category/{url_key}
        if (preg_match('#^' . preg_quote($faqUrlKey, '#') . '/category/([a-z0-9-]+)$#', $identifier, $matches)) {
            $urlKey = $matches[1];

            // Load category by URL key and get ID
            $categoryId = $this->getCategoryIdByUrlKey($urlKey);
            if ($categoryId) {
                $request->setModuleName('faq')
                    ->setControllerName('category')
                    ->setActionName('view')
                    ->setParam('id', $categoryId)
                    ->setPathInfo('/faq/category/view/id/' . $categoryId);

                return $this->actionFactory->create(
                    Forward::class,
                    ['request' => $request]
                );
            }
        }

        return null;
    }

    /**
     * Get FAQ item ID by URL key
     *
     * @param string $urlKey
     * @return int|null
     */
    protected function getItemIdByUrlKey($urlKey)
    {
        try {
            $connection = $this->resourceConnection->getConnection();

            $select = $connection->select()
                ->from($this->resourceConnection->getTableName('panth_faq_item'), ['item_id'])
                ->where('url_key = ?', $urlKey)
                ->where('is_active = ?', 1)
                ->limit(1);

            $itemId = $connection->fetchOne($select);
            return $itemId ? (int)$itemId : null;
        } catch (\Exception $e) {
            $this->logger->error('Error loading FAQ item by URL key: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get FAQ category ID by URL key
     *
     * @param string $urlKey
     * @return int|null
     */
    protected function getCategoryIdByUrlKey($urlKey)
    {
        try {
            $connection = $this->resourceConnection->getConnection();

            $select = $connection->select()
                ->from($this->resourceConnection->getTableName('panth_faq_category'), ['category_id'])
                ->where('url_key = ?', $urlKey)
                ->where('is_active = ?', 1)
                ->limit(1);

            $categoryId = $connection->fetchOne($select);
            return $categoryId ? (int)$categoryId : null;
        } catch (\Exception $e) {
            $this->logger->error('Error loading FAQ category by URL key: ' . $e->getMessage());
            return null;
        }
    }
}
