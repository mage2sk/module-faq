<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Model\Category;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Panth\Faq\Api\CategoryRepositoryInterface;
use Panth\Faq\Logger\Logger;
use Panth\Faq\Model\Category;
use Panth\Faq\Model\Category\DataProvider;
use Panth\Faq\Model\ResourceModel\Category as CategoryResource;
use Panth\Faq\Model\ResourceModel\Category\Collection;
use Panth\Faq\Model\ResourceModel\Category\CollectionFactory;
use Panth\Faq\Test\Unit\EntityTrait;
use PHPUnit\Framework\TestCase;

class DataProviderTest extends TestCase
{
    use EntityTrait;

    private array $categories = [];
    private ?int $store = null;
    private $persisted = null;
    private array $cleared = [];
    private CategoryRepositoryInterface $repository;
    private Logger $logger;

    protected function setUp(): void
    {
        $this->repository = $this->createStub(CategoryRepositoryInterface::class);
        $this->logger = $this->createStub(Logger::class);
    }

    private function provider(array $meta = []): DataProvider
    {
        $collection = $this->createStub(Collection::class);
        $collection->method('getItems')->willReturnCallback(fn () => $this->categories);
        $collection->method('getNewEmptyItem')->willReturnCallback(fn () => $this->newCategory());
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);
        $persistor = $this->createStub(DataPersistorInterface::class);
        $persistor->method('get')->willReturnCallback(fn () => $this->persisted);
        $persistor->method('clear')->willReturnCallback(function ($key) {
            $this->cleared[] = $key;
        });
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(
            fn ($key, $default = null) => $key === 'store' && $this->store !== null ? $this->store : $default
        );

        return new DataProvider(
            'faq_category_form_data_source',
            'category_id',
            'category_id',
            $factory,
            $persistor,
            $this->logger,
            $this->repository,
            $request,
            $meta
        );
    }

    public function testMetaOnDefaultAndStoreScope(): void
    {
        $this->assertSame([], $this->provider()->getMeta());

        $this->store = 3;
        $meta = $this->provider()->getMeta();

        $this->assertSame(
            ['name', 'url_key', 'description', 'icon', 'is_active'],
            array_values(array_intersect(
                ['name', 'url_key', 'description', 'icon', 'is_active'],
                array_keys($meta['general']['children'])
            ))
        );
        $this->assertSame(
            '${ $.provider }:data.use_default.meta_keywords',
            $meta['search_engine_optimization']['children']['meta_keywords']['arguments']['data']['config']['imports']['isUseDefault']
        );
    }

    public function testDefaultScopeDataWithoutIcon(): void
    {
        $this->categories = [
            $this->newCategory(['category_id' => 4, 'name' => 'Shipping']),
            $this->newCategory(['category_id' => 5, 'name' => 'Legacy', 'icon' => '[{"name":"x.png"}]']),
        ];

        $data = $this->provider()->getData();

        $this->assertSame(['category_id' => 4, 'name' => 'Shipping', 'store_scope_id' => 0, 'icon' => null], $data[4]);
        $this->assertNull($data[5]['icon']);
    }

    public function testStoreScopeUsesScopedModel(): void
    {
        $this->store = 2;
        $this->categories = [$this->newCategory(['category_id' => 4, 'name' => 'Default'])];
        $resource = $this->createStub(CategoryResource::class);
        $resource->method('getIdFieldName')->willReturn('category_id');
        $resource->method('load')->willReturnCallback(function ($model, $id) use ($resource) {
            $model->addData(['category_id' => $id, 'name' => 'Scoped ' . $model->getData('store_scope_id')]);
            return $resource;
        });
        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(ManagerInterface::class));
        $this->repository = $this->createStub(CategoryRepositoryInterface::class);
        $this->repository->method('getById')->willReturn(new Category($context, $this->createStub(Registry::class), $resource));

        $data = $this->provider()->getData();

        $this->assertSame('Scoped 2', $data[4]['name']);
        $this->assertSame(2, $data[4]['store_scope_id']);
    }

    public function testPersistedDataIsUsedOnce(): void
    {
        $this->persisted = ['name' => 'Draft'];

        $data = $this->provider()->getData();

        $this->assertSame(['name' => 'Draft'], $data['']);
        $this->assertSame(['panth_faq_category'], $this->cleared);
    }

    public function testErrorsAreLogged(): void
    {
        $this->store = 1;
        $this->categories = [$this->newCategory(['category_id' => 4])];
        $this->repository = $this->createStub(CategoryRepositoryInterface::class);
        $this->repository->method('getById')->willThrowException(new \RuntimeException('deleted'));
        $logger = $this->createMock(Logger::class);
        $logger->expects($this->once())->method('error')->with('FAQ Category DataProvider error: deleted');
        $this->logger = $logger;

        $this->assertSame([], $this->provider()->getData());
    }
}
