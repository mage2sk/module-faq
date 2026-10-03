<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Model\ResourceModel;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Faq\Model\ResourceModel\Category as CategoryResource;
use Panth\Faq\Test\Unit\EntityTrait;
use PHPUnit\Framework\TestCase;

class CategoryTest extends TestCase
{
    use EntityTrait;
    use FakeConnectionTrait;

    private array $events = [];
    private string $area = Area::AREA_FRONTEND;

    private function resource(): CategoryResource
    {
        $state = $this->createStub(State::class);
        $state->method('getAreaCode')->willReturnCallback(fn () => $this->area);
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(6);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $events = $this->createStub(ManagerInterface::class);
        $events->method('dispatch')->willReturnCallback(function ($name, $data) {
            $this->events[] = [$name, $data];
        });

        return new class ($this->fakeConnection(), $storeManager, $events, $state) extends CategoryResource {
            public function __construct(private AdapterInterface $fake, $storeManager, $eventManager, $appState)
            {
                $this->storeManager = $storeManager;
                $this->eventManager = $eventManager;
                $this->appState = $appState;
            }

            public function getConnection()
            {
                return $this->fake;
            }

            public function getMainTable()
            {
                return 'panth_faq_category';
            }

            public function getTable($tableName)
            {
                return 'pfx_' . $tableName;
            }
        };
    }

    public function testStoreOverrideRowAndDefaults(): void
    {
        $resource = $this->resource();
        $this->assertSame([], $resource->getStoreOverrideRow(0, 2));
        $this->assertSame([], $resource->loadDefaultValuesPublic(0));

        $this->rowResults = [['name' => 'Store'], ['url_key' => 'default']];
        $this->assertSame(['name' => 'Store'], $resource->getStoreOverrideRow(4, 2));
        $this->assertSame(['url_key' => 'default'], $resource->loadDefaultValuesPublic(4));
        $this->assertSame([['category_id = ?', 4], ['store_id = ?', 2]], $this->queries[0]['where']);
        $this->assertSame(['panth_faq_category', CategoryResource::SCOPED_FIELDS], $this->queries[1]['from']);
    }

    public function testCategoryIdByUrlKey(): void
    {
        $resource = $this->resource();
        $this->assertNull($resource->getCategoryIdByUrlKeyForStore('', 2));

        $this->oneResults = ['5'];
        $this->assertSame(5, $resource->getCategoryIdByUrlKeyForStore('scoped', 2));

        $this->oneResults = [false, '7'];
        $this->assertSame(7, $resource->getCategoryIdByUrlKeyForStore('default', 2));
        $this->assertSame('v.category_id = m.category_id AND v.store_id = 2', $this->queries[2]['join'][1]);

        $this->oneResults = [];
        $this->assertNull($resource->getCategoryIdByUrlKeyForStore('missing', 0));
        $this->assertCount(4, $this->queries);
    }

    public function testResolveStoreScopeUsesFrontendStoreOnly(): void
    {
        $resource = $this->resource();
        $this->assertSame(6, $this->invoke($resource, 'resolveStoreScopeId', $this->newCategory()));
        $this->assertSame(9, $this->invoke($resource, 'resolveStoreScopeId', $this->newCategory(['store_scope_id' => 9])));

        $this->area = Area::AREA_CRONTAB;
        $this->assertSame(0, $this->invoke($resource, 'resolveStoreScopeId', $this->newCategory()));
    }

    public function testBeforeAndAfterSaveRoundTripForStoreScope(): void
    {
        $resource = $this->resource();
        $category = $this->newCategory([
            'category_id' => 4,
            'store_scope_id' => 2,
            'name' => 'Store name',
            'icon' => 'store.png',
            'stores' => ['2'],
            'use_default' => ['icon'],
        ]);
        $this->rowResults = [['name' => 'Default name', 'icon' => 'default.png']];

        $this->invoke($resource, '_beforeSave', $category);
        $this->assertSame('Default name', $category->getName());
        $this->assertSame('default.png', $category->getIcon());

        $this->invoke($resource, '_afterSave', $category);

        $this->assertSame('Store name', $category->getName());
        $this->assertSame(
            ['delete', 'pfx_panth_faq_category_store', ['category_id = ?' => 4]],
            $this->writes[0]
        );
        $this->assertSame(
            ['insertMultiple', 'pfx_panth_faq_category_store', [['category_id' => 4, 'store_id' => '2']]],
            $this->writes[1]
        );
        $this->assertSame('insertOnDuplicate', $this->writes[2][0]);
        $this->assertSame('Store name', $this->writes[2][2]['name']);
        $this->assertNull($this->writes[2][2]['icon']);
        $this->assertSame('panth_faq_category_save_after', $this->events[0][0]);
        $this->assertSame($category, $this->events[0][1]['category']);
    }

    public function testSaveStoreValuesDeletesWhenNothingOverridden(): void
    {
        $resource = $this->resource();

        $this->invoke($resource, 'saveStoreValues', $this->newCategory(['category_id' => 4, 'store_scope_id' => 2]));

        $this->assertSame(
            [['delete', 'pfx_panth_faq_category_value', ['category_id = ?' => 4, 'store_id = ?' => 2]]],
            $this->writes
        );
    }

    public function testAfterLoadLoadsStoresAndMergesOverrides(): void
    {
        $resource = $this->resource();
        $category = $this->newCategory(['category_id' => 4, 'store_scope_id' => 2, 'name' => 'Default']);
        $this->colResults = [['0', '2']];
        $this->rowResults = [['name' => 'Scoped', 'description' => null]];

        $this->invoke($resource, '_afterLoad', $category);

        $this->assertSame(['0', '2'], $category->getData('stores'));
        $this->assertSame('Scoped', $category->getName());
        $this->assertSame('Default', $category->getData('store_default_values')['name']);
        $this->assertArrayHasKey('description', $category->getData('use_default'));
        $this->assertArrayNotHasKey('name', $category->getData('use_default'));
    }

    public function testAfterDeleteRemovesRewrites(): void
    {
        $resource = $this->resource();

        $this->invoke($resource, '_afterDelete', $this->newCategory(['category_id' => 4]));

        $this->assertSame(
            [['delete', 'pfx_url_rewrite', ['entity_type = ?' => 'faq_category', 'entity_id = ?' => 4]]],
            $this->writes
        );
    }
}
