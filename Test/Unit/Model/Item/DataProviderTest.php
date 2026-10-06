<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Model\Item;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Panth\Faq\Api\ItemRepositoryInterface;
use Panth\Faq\Logger\Logger;
use Panth\Faq\Model\Item;
use Panth\Faq\Model\Item\DataProvider;
use Panth\Faq\Model\ResourceModel\Item as ItemResource;
use Panth\Faq\Model\ResourceModel\Item\Collection;
use Panth\Faq\Model\ResourceModel\Item\CollectionFactory;
use Panth\Faq\Test\Unit\EntityTrait;
use PHPUnit\Framework\TestCase;

class DataProviderTest extends TestCase
{
    use EntityTrait;

    private array $items = [];
    private ?int $store = null;
    private int $entityId = 0;
    private $persisted = null;
    private array $cleared = [];
    private array $categoryIds = [];
    private ItemRepositoryInterface $repository;
    private Logger $logger;

    protected function setUp(): void
    {
        $this->repository = $this->createStub(ItemRepositoryInterface::class);
        $this->logger = $this->createStub(Logger::class);
    }

    private function provider(array $meta = []): DataProvider
    {
        $collection = $this->createStub(Collection::class);
        $collection->method('getItems')->willReturnCallback(fn () => $this->items);
        $collection->method('getNewEmptyItem')->willReturnCallback(fn () => $this->newItem());
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $persistor = $this->createStub(DataPersistorInterface::class);
        $persistor->method('get')->willReturnCallback(fn () => $this->persisted);
        $persistor->method('clear')->willReturnCallback(function ($key) {
            $this->cleared[] = $key;
        });

        $lastItem = null;
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnCallback(function ($cond, $value) use ($select, &$lastItem) {
            $lastItem = $value;
            return $select;
        });
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchCol')->willReturnCallback(function () use (&$lastItem) {
            return $this->categoryIds[$lastItem] ?? [];
        });
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(
            fn ($key, $default = null) => match (true) {
                $key === 'store' && $this->store !== null => (string)$this->store,
                $key === 'item_id' && $this->entityId > 0 => (string)$this->entityId,
                default => $default,
            }
        );

        return new DataProvider(
            'faq_item_form_data_source',
            'item_id',
            'item_id',
            $factory,
            $persistor,
            $this->logger,
            $resource,
            $this->repository,
            $request,
            $meta
        );
    }

    public function testMetaIsUntouchedOnDefaultScope(): void
    {
        $meta = ['general' => ['arguments' => ['data' => ['config' => ['label' => 'General']]]]];

        $this->assertSame($meta, $this->provider($meta)->getMeta());
    }

    public function testMetaAddsUseDefaultServiceOnStoreScope(): void
    {
        $this->store = 2;

        $meta = $this->provider()->getMeta();

        $question = $meta['general']['children']['question']['arguments']['data']['config'];
        $this->assertSame('ui/form/element/helper/service', $question['service']['template']);
        $this->assertSame('${ $.provider }:data.use_default.question', $question['imports']['isUseDefault']);
        $this->assertArrayHasKey('url_key', $meta['search_engine_optimization']['children']);
        $this->assertCount(4, $meta['general']['children']);
        $this->assertCount(4, $meta['search_engine_optimization']['children']);
    }

    public function testDefaultScopeDataIncludesFaqCategories(): void
    {
        $this->items = [$this->newItem(['item_id' => 3, 'question' => 'Q3'])];
        $this->categoryIds = [3 => ['4', '9']];

        $provider = $this->provider();
        $data = $provider->getData();

        $this->assertSame(
            [3 => ['item_id' => 3, 'question' => 'Q3', 'category_id' => [4, 9], 'store_scope_id' => 0]],
            $data
        );
        $this->items = [];
        $this->assertSame($data, $provider->getData());
    }

    public function testStoreScopeLoadsScopedValues(): void
    {
        $this->store = 2;
        $this->items = [$this->newItem(['item_id' => 3, 'question' => 'Default'])];
        $scopedResource = $this->createStub(ItemResource::class);
        $scopedResource->method('getIdFieldName')->willReturn('item_id');
        $scopedResource->method('load')->willReturnCallback(function ($model, $id) use ($scopedResource) {
            $model->addData(['item_id' => $id, 'question' => 'Store ' . $model->getData('store_scope_id')]);
            return $scopedResource;
        });
        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(ManagerInterface::class));
        $scoped = new Item($context, $this->createStub(Registry::class), $scopedResource);
        $this->repository = $this->createStub(ItemRepositoryInterface::class);
        $this->repository->method('getById')->willReturn($scoped);

        $data = $this->provider()->getData();

        $this->assertSame('Store 2', $data[3]['question']);
        $this->assertSame(2, $data[3]['store_scope_id']);
        $this->assertSame([], $data[3]['category_id']);
    }

    public function testPersistedFormDataIsMergedAndCleared(): void
    {
        $this->persisted = ['item_id' => 12, 'question' => 'Unsaved'];

        $data = $this->provider()->getData();

        $this->assertSame(['item_id' => 12, 'question' => 'Unsaved'], $data[12]);
        $this->assertSame(['panth_faq_item'], $this->cleared);
    }

    public function testErrorsAreLoggedAndEmptyArrayReturned(): void
    {
        $this->store = 1;
        $this->items = [$this->newItem(['item_id' => 3])];
        $this->repository = $this->createStub(ItemRepositoryInterface::class);
        $this->repository->method('getById')->willThrowException(new \RuntimeException('gone'));
        $logger = $this->createMock(Logger::class);
        $logger->expects($this->once())->method('error')
            ->with('FAQ Item DataProvider error: gone', $this->arrayHasKey('trace'));
        $this->logger = $logger;

        $this->assertSame([], $this->provider()->getData());
    }

    public function testStoreScopeDisablesOnlyInheritedFields(): void
    {
        $this->store = 2;
        $this->entityId = 7;
        $resource = $this->createStub(ItemResource::class);
        $resource->method('getIdFieldName')->willReturn('item_id');
        $resource->method('getStoreOverrideRow')->willReturnCallback(
            fn ($id, $store) => $id === 7 && $store === 2 ? ['question' => 'Luma question', 'answer' => null] : []
        );
        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(ManagerInterface::class));
        $this->repository = $this->createStub(ItemRepositoryInterface::class);
        $this->repository->method('getById')->willReturn(new Item($context, $this->createStub(Registry::class), $resource));

        $meta = $this->provider()->getMeta();

        $this->assertFalse($meta['general']['children']['question']['arguments']['data']['config']['disabled']);
        $this->assertTrue($meta['general']['children']['answer']['arguments']['data']['config']['disabled']);
        $this->assertTrue($meta['search_engine_optimization']['children']['url_key']['arguments']['data']['config']['disabled']);
    }

    public function testStoreScopeWithoutEntityDisablesAllScopedFields(): void
    {
        $this->store = 2;

        $meta = $this->provider()->getMeta();

        foreach (['question', 'answer', 'is_active', 'show_on_main'] as $field) {
            $this->assertTrue($meta['general']['children'][$field]['arguments']['data']['config']['disabled']);
        }
    }

    public function testStoreScopeLookupFailureFallsBackToInherited(): void
    {
        $this->store = 2;
        $this->entityId = 9;
        $this->repository = $this->createStub(ItemRepositoryInterface::class);
        $this->repository->method('getById')->willThrowException(new \RuntimeException('gone'));

        $meta = $this->provider()->getMeta();

        $this->assertTrue($meta['general']['children']['question']['arguments']['data']['config']['disabled']);
    }
}
