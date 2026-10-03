<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Block\Adminhtml\Category\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Faq\Api\CategoryRepositoryInterface;
use Panth\Faq\Block\Adminhtml\Category\Edit\ViewOnStorefrontButton;
use Panth\Faq\Model\ResourceModel\Category as CategoryResource;
use PHPUnit\Framework\TestCase;

class ViewOnStorefrontButtonTest extends TestCase
{
    protected array $params = [];
    protected bool $exists = true;
    protected ?int $defaultStoreId = 1;
    protected array $overrides = [];
    protected array $defaults = ['url_key' => 'default-slug'];
    protected array $routes = [];

    protected function idParam(): string
    {
        return 'category_id';
    }

    protected function segment(): string
    {
        return 'category';
    }

    protected function repositoryClass(): string
    {
        return CategoryRepositoryInterface::class;
    }

    protected function resourceClass(): string
    {
        return CategoryResource::class;
    }

    protected function buttonClass(): string
    {
        return ViewOnStorefrontButton::class;
    }

    protected function button(): object
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(
            fn ($key, $default = null) => $this->params[$key] ?? $default
        );
        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);

        $repository = $this->createStub($this->repositoryClass());
        $repository->method('getById')->willReturnCallback(function () {
            if (!$this->exists) {
                throw new NoSuchEntityException(__('gone'));
            }
            return null;
        });
        $resource = $this->createStub($this->resourceClass());
        $resource->method('getStoreOverrideRow')->willReturnCallback(
            fn ($id, $storeId) => $this->overrides[$storeId] ?? []
        );
        $resource->method('loadDefaultValuesPublic')->willReturnCallback(fn () => $this->defaults);

        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getDefaultStoreView')->willReturnCallback(function () {
            if ($this->defaultStoreId === null) {
                return null;
            }
            $store = $this->createStub(Store::class);
            $store->method('getId')->willReturn($this->defaultStoreId);
            return $store;
        });
        $storeManager->method('getStore')->willReturnCallback(function ($id) {
            $store = $this->createStub(Store::class);
            $store->method('getBaseUrl')->willReturn('https://store' . $id . '.test/');
            return $store;
        });
        $config = $this->createStub(ScopeConfigInterface::class);
        $config->method('getValue')->willReturnCallback(
            fn ($path, $scope = null, $storeId = null) => $this->routes[$storeId] ?? null
        );
        $class = $this->buttonClass();

        return new $class($context, $repository, $resource, $storeManager, $config);
    }

    public function testNoButtonWithoutEntityId(): void
    {
        $this->assertSame([], $this->button()->getButtonData());
    }

    public function testNoButtonWhenEntityIsMissing(): void
    {
        $this->params[$this->idParam()] = '4';
        $this->exists = false;

        $this->assertSame([], $this->button()->getButtonData());
    }

    public function testDefaultScopeUsesDefaultStoreViewAndDefaultSlug(): void
    {
        $this->params[$this->idParam()] = '4';
        $this->overrides[1] = ['url_key' => 'ignored-on-default-scope'];

        $data = $this->button()->getButtonData();

        $this->assertSame(
            "window.open('https://store1.test/faq/" . $this->segment() . "/default-slug', '_blank');",
            $data['on_click']
        );
        $this->assertSame('View on Storefront', (string)$data['label']);
        $this->assertSame('view', $data['class']);
    }

    public function testNoDefaultStoreViewMeansNoButton(): void
    {
        $this->params[$this->idParam()] = '4';
        $this->defaultStoreId = null;

        $this->assertSame([], $this->button()->getButtonData());
    }

    public function testStoreScopeUsesOverrideSlugAndStoreRoute(): void
    {
        $this->params = [$this->idParam() => '4', 'store' => '2'];
        $this->overrides[2] = ['url_key' => 'store-slug'];
        $this->routes[2] = '/help/';

        $this->assertSame(
            "window.open('https://store2.test/help/" . $this->segment() . "/store-slug', '_blank');",
            $this->button()->getButtonData()['on_click']
        );
    }

    public function testStoreScopeFallsBackToDefaultSlug(): void
    {
        $this->params = [$this->idParam() => '4', 'store' => '3'];
        $this->overrides[3] = ['url_key' => ''];

        $this->assertStringContainsString(
            'https://store3.test/faq/' . $this->segment() . '/default-slug',
            $this->button()->getButtonData()['on_click']
        );
    }

    public function testMissingSlugMeansNoButton(): void
    {
        $this->params[$this->idParam()] = '4';
        $this->defaults = [];

        $this->assertSame([], $this->button()->getButtonData());
    }
}
