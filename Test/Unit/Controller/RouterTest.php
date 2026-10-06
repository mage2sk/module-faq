<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Controller;

use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\ResponseInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Faq\Controller\Router;
use Panth\Faq\Helper\Data as FaqHelper;
use Panth\Faq\Model\ResourceModel\Category as CategoryResource;
use Panth\Faq\Model\ResourceModel\Item as ItemResource;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    private bool $enabled = true;
    private ?string $route = null;
    private array $itemIds = [];
    private array $categoryIds = [];
    private ?\Throwable $resourceError = null;
    private array $set = [];
    private array $forwarded = [];
    private LoggerInterface $logger;
    private ActionInterface $action;

    protected function setUp(): void
    {
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->action = $this->createStub(ActionInterface::class);
    }

    private function request(string $path, bool $dispatched = false): Http
    {
        $request = $this->createStub(Http::class);
        $request->method('isDispatched')->willReturn($dispatched);
        $request->method('getPathInfo')->willReturn($path);
        foreach (['setModuleName', 'setControllerName', 'setActionName', 'setPathInfo'] as $setter) {
            $request->method($setter)->willReturnCallback(function ($value) use ($setter, $request) {
                $this->set[$setter] = $value;
                return $request;
            });
        }
        $request->method('setParam')->willReturnCallback(function ($key, $value) use ($request) {
            $this->set['params'][$key] = $value;
            return $request;
        });

        return $request;
    }

    private function router(): Router
    {
        $actionFactory = $this->createStub(ActionFactory::class);
        $actionFactory->method('create')->willReturnCallback(function ($class, $args) {
            $this->forwarded[] = $class;
            return $this->action;
        });
        $config = $this->createStub(ScopeConfigInterface::class);
        $config->method('getValue')->willReturnCallback(fn () => $this->route);
        $helper = $this->createStub(FaqHelper::class);
        $helper->method('isEnabled')->willReturnCallback(fn () => $this->enabled);
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn('2');
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $itemResource = $this->createStub(ItemResource::class);
        $itemResource->method('getItemIdByUrlKeyForStore')->willReturnCallback(function ($key, $storeId) {
            if ($this->resourceError) {
                throw $this->resourceError;
            }
            return $this->itemIds[$key . '@' . $storeId] ?? null;
        });
        $categoryResource = $this->createStub(CategoryResource::class);
        $categoryResource->method('getCategoryIdByUrlKeyForStore')->willReturnCallback(function ($key, $storeId) {
            if ($this->resourceError) {
                throw $this->resourceError;
            }
            return $this->categoryIds[$key . '@' . $storeId] ?? null;
        });

        return new Router(
            $actionFactory,
            $this->createStub(ResponseInterface::class),
            $config,
            $helper,
            $this->logger,
            $this->createStub(ResourceConnection::class),
            $storeManager,
            $itemResource,
            $categoryResource
        );
    }

    public function testDispatchedRequestIsIgnored(): void
    {
        $this->assertNull($this->router()->match($this->request('/faq', true)));
        $this->assertSame([], $this->forwarded);
    }

    public function testDisabledModuleDoesNotMatch(): void
    {
        $this->enabled = false;

        $this->assertNull($this->router()->match($this->request('/faq')));
    }

    public function testDefaultRouteForwardsToIndex(): void
    {
        $result = $this->router()->match($this->request('/faq/'));

        $this->assertSame($this->action, $result);
        $this->assertSame([Forward::class], $this->forwarded);
        $this->assertSame(
            [
                'setModuleName' => 'faq',
                'setControllerName' => 'index',
                'setActionName' => 'index',
                'setPathInfo' => '/faq/index/index',
            ],
            $this->set
        );
    }

    public function testCustomRouteWithIndexSuffixMatches(): void
    {
        $this->route = '/help-center/';

        $this->assertSame($this->action, $this->router()->match($this->request('/help-center/index')));
        $this->assertSame('/faq/index/index', $this->set['setPathInfo']);
    }

    public function testDefaultFaqPathIsNotMatchedWhenRouteIsCustom(): void
    {
        $this->route = 'help';

        $this->assertNull($this->router()->match($this->request('/faq')));
    }

    public function testItemUrlKeyForwardsToView(): void
    {
        $this->itemIds['how-to-return@2'] = 15;

        $this->router()->match($this->request('/faq/item/how-to-return'));

        $this->assertSame('view', $this->set['setActionName']);
        $this->assertSame('index', $this->set['setControllerName']);
        $this->assertSame(['id' => 15], $this->set['params']);
        $this->assertSame('/faq/index/view/id/15', $this->set['setPathInfo']);
    }

    public function testUnknownItemForwardsToNoRoute(): void
    {
        $this->assertSame($this->action, $this->router()->match($this->request('/faq/item/missing')));
        $this->assertSame('cms', $this->set['setModuleName']);
        $this->assertSame('noroute', $this->set['setControllerName']);
        $this->assertSame('/cms/noroute/index', $this->set['setPathInfo']);
    }

    public function testCategoryUrlKeyForwardsToCategoryView(): void
    {
        $this->route = 'support';
        $this->categoryIds['shipping@2'] = 4;

        $this->router()->match($this->request('/support/category/shipping'));

        $this->assertSame('category', $this->set['setControllerName']);
        $this->assertSame(['id' => 4], $this->set['params']);
        $this->assertSame('/faq/category/view/id/4', $this->set['setPathInfo']);
    }

    public function testResourceErrorsAreLoggedAndTreatedAsNotFound(): void
    {
        $this->resourceError = new \RuntimeException('db gone');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')
            ->with('Error loading FAQ category by URL key: db gone');
        $this->logger = $logger;

        $this->router()->match($this->request('/faq/category/shipping'));

        $this->assertSame('noroute', $this->set['setControllerName']);
    }

    public function testItemResourceErrorIsLogged(): void
    {
        $this->resourceError = new \RuntimeException('timeout');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with('Error loading FAQ item by URL key: timeout');
        $this->logger = $logger;

        $this->router()->match($this->request('/faq/item/abc'));

        $this->assertSame('cms', $this->set['setModuleName']);
    }

    public function testInvalidSlugsAndOtherPathsAreIgnored(): void
    {
        $router = $this->router();

        $this->assertNull($router->match($this->request('/faq/item/Upper_Case')));
        $this->assertNull($router->match($this->request('/faq/item/a/b')));
        $this->assertNull($router->match($this->request('/catalog/product/view')));
        $this->assertSame([], $this->forwarded);
    }
}
