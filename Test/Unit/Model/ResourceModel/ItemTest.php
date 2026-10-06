<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Model\ResourceModel;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Faq\Model\ResourceModel\Item as ItemResource;
use Panth\Faq\Test\Unit\EntityTrait;
use PHPUnit\Framework\TestCase;

class ItemTest extends TestCase
{
    use EntityTrait;
    use FakeConnectionTrait;

    private array $events = [];
    private $area = Area::AREA_FRONTEND;
    private int $currentStore = 1;

    private function resource(): ItemResource
    {
        $state = $this->createStub(State::class);
        $state->method('getAreaCode')->willReturnCallback(function () {
            if ($this->area instanceof \Throwable) {
                throw $this->area;
            }
            return $this->area;
        });
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturnCallback(fn () => $this->currentStore);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $events = $this->createStub(ManagerInterface::class);
        $events->method('dispatch')->willReturnCallback(function ($name, $data) {
            $this->events[] = [$name, $data];
        });

        return new class ($this->fakeConnection(), $storeManager, $events, $state) extends ItemResource {
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
                return 'panth_faq_item';
            }

            public function getTable($tableName)
            {
                return 'pfx_' . $tableName;
            }
        };
    }

    public function testStoreOverrideRowGuardsAndReturnsRow(): void
    {
        $resource = $this->resource();
        $this->assertSame([], $resource->getStoreOverrideRow(0, 1));
        $this->assertSame([], $resource->getStoreOverrideRow(1, 0));
        $this->assertSame([], $this->queries);

        $this->rowResults = [['question' => 'Q', 'answer' => null]];
        $this->assertSame(['question' => 'Q', 'answer' => null], $resource->getStoreOverrideRow(3, 2));
        $this->assertSame(['pfx_panth_faq_item_value', ItemResource::SCOPED_FIELDS], $this->queries[0]['from']);
        $this->assertSame([['item_id = ?', 3], ['store_id = ?', 2]], $this->queries[0]['where']);

        $this->assertSame([], $resource->getStoreOverrideRow(3, 2));
    }

    public function testStoreOverridesDropNullValues(): void
    {
        $resource = $this->resource();
        $this->assertSame([], $resource->getStoreOverrides(-1, 1));

        $this->rowResults = [['question' => 'Q', 'answer' => null, 'is_active' => '0']];
        $this->assertSame(['question' => 'Q', 'is_active' => '0'], $resource->getStoreOverrides(3, 2));
        $this->assertSame([], $resource->getStoreOverrides(3, 2));
    }

    public function testLoadDefaultValues(): void
    {
        $resource = $this->resource();
        $this->assertSame([], $resource->loadDefaultValuesPublic(0));

        $this->rowResults = [['url_key' => 'default-key']];
        $this->assertSame(['url_key' => 'default-key'], $resource->loadDefaultValuesPublic(5));
        $this->assertSame(['panth_faq_item', ItemResource::SCOPED_FIELDS], $this->queries[0]['from']);
        $this->assertSame([], $resource->loadDefaultValuesPublic(5));
    }

    public function testItemIdByUrlKeyPrefersStoreValue(): void
    {
        $resource = $this->resource();
        $this->assertNull($resource->getItemIdByUrlKeyForStore('', 1));

        $this->oneResults = ['12'];
        $this->assertSame(12, $resource->getItemIdByUrlKeyForStore('store-key', 2));
        $this->assertCount(1, $this->queries);
        $this->assertSame(['v' => 'pfx_panth_faq_item_value'], $this->queries[0]['from'][0]);
        $this->assertContains(['v.url_key = ?', 'store-key'], $this->queries[0]['where']);
    }

    public function testItemIdByUrlKeyFallsBackToDefaultKey(): void
    {
        $resource = $this->resource();
        $this->oneResults = [false, '8'];

        $this->assertSame(8, $resource->getItemIdByUrlKeyForStore('default-key', 2));
        $this->assertCount(2, $this->queries);
        $this->assertSame(['m' => 'panth_faq_item'], $this->queries[1]['from'][0]);
        $this->assertSame('v.item_id = m.item_id AND v.store_id = 2', $this->queries[1]['join'][1]);
        $this->assertContains(['m.url_key = ?', 'default-key'], $this->queries[1]['where']);
    }

    public function testItemIdByUrlKeyOnDefaultStoreSkipsScopedLookup(): void
    {
        $resource = $this->resource();

        $this->assertNull($resource->getItemIdByUrlKeyForStore('missing', 0));
        $this->assertCount(1, $this->queries);
        $this->assertSame('v.item_id = m.item_id AND v.store_id = 0', $this->queries[0]['join'][1]);
    }

    public function testResolveStoreScopeId(): void
    {
        $resource = $this->resource();

        $this->assertSame(4, $this->invoke($resource, 'resolveStoreScopeId', $this->newItem(['store_scope_id' => '4'])));
        $this->assertSame(1, $this->invoke($resource, 'resolveStoreScopeId', $this->newItem()));

        $this->currentStore = 0;
        $this->assertSame(0, $this->invoke($resource, 'resolveStoreScopeId', $this->newItem()));

        $this->area = Area::AREA_ADMINHTML;
        $this->currentStore = 1;
        $this->assertSame(0, $this->invoke($resource, 'resolveStoreScopeId', $this->newItem()));

        $this->area = new LocalizedException(__('Area code is not set'));
        $this->assertSame(0, $this->invoke($resource, 'resolveStoreScopeId', $this->newItem()));
    }

    public function testMergeStoreOverridesAppliesNonNullValues(): void
    {
        $resource = $this->resource();
        $item = $this->newItem(['item_id' => 3, 'question' => 'Default Q', 'answer' => 'Default A']);

        $this->invoke($resource, 'mergeStoreOverrides', $item);
        $this->assertFalse($item->hasData('use_default'));

        $item->setData('store_scope_id', 2);
        $this->rowResults = [['question' => 'Store Q', 'answer' => null, 'is_active' => '0']];
        $this->invoke($resource, 'mergeStoreOverrides', $item);

        $this->assertSame('Store Q', $item->getQuestion());
        $this->assertSame('Default A', $item->getAnswer());
        $this->assertSame('0', $item->getIsActive());
        $this->assertSame('Default Q', $item->getData('store_default_values')['question']);
        $useDefault = $item->getData('use_default');
        $this->assertArrayNotHasKey('question', $useDefault);
        $this->assertArrayNotHasKey('is_active', $useDefault);
        $this->assertSame(1, $useDefault['answer']);
        $this->assertSame(1, $useDefault['meta_keywords']);
    }

    public function testSaveStoreValuesUpsertsScopedRow(): void
    {
        $resource = $this->resource();
        $item = $this->newItem([
            'item_id' => 3,
            'store_scope_id' => 2,
            'question' => 'Store Q',
            'answer' => '',
            'url_key' => 'k',
            'use_default' => ['url_key'],
        ]);

        $this->invoke($resource, 'saveStoreValues', $item);

        $this->assertSame('insertOnDuplicate', $this->writes[0][0]);
        $this->assertSame('pfx_panth_faq_item_value', $this->writes[0][1]);
        $row = $this->writes[0][2];
        $this->assertSame(3, $row['item_id']);
        $this->assertSame(2, $row['store_id']);
        $this->assertSame('Store Q', $row['question']);
        $this->assertNull($row['answer']);
        $this->assertNull($row['url_key']);
        $this->assertSame(ItemResource::SCOPED_FIELDS, $this->writes[0][3]);
    }

    public function testSaveStoreValuesDeletesRowWhenEverythingInherits(): void
    {
        $resource = $this->resource();
        $item = $this->newItem(['item_id' => 3, 'store_scope_id' => 2, 'question' => 'Q', 'use_default' => ['question']]);

        $this->invoke($resource, 'saveStoreValues', $item);

        $this->assertSame(
            [['delete', 'pfx_panth_faq_item_value', ['item_id = ?' => 3, 'store_id = ?' => 2]]],
            $this->writes
        );
    }

    public function testSaveStoreValuesSkipsDefaultScopeAndNewItems(): void
    {
        $resource = $this->resource();
        $this->invoke($resource, 'saveStoreValues', $this->newItem(['item_id' => 3, 'question' => 'Q']));
        $this->invoke($resource, 'saveStoreValues', $this->newItem(['store_scope_id' => 2, 'question' => 'Q']));

        $this->assertSame([], $this->writes);
    }

    public function testBeforeSaveKeepsDefaultsInMainTableForStoreScope(): void
    {
        $resource = $this->resource();
        $item = $this->newItem(['item_id' => 3, 'store_scope_id' => 2, 'question' => 'Store Q', 'sort_order' => 5]);
        $this->rowResults = [['question' => 'Default Q', 'answer' => 'Default A']];

        $this->invoke($resource, '_beforeSave', $item);

        $this->assertSame('Default Q', $item->getQuestion());
        $this->assertSame('Default A', $item->getAnswer());
        $this->assertSame('Store Q', $item->getData('_panth_scope_snapshot')['question']);
        $this->assertSame(5, $item->getData('sort_order'));
    }

    public function testBeforeSaveOnDefaultScopeChangesNothing(): void
    {
        $resource = $this->resource();
        $item = $this->newItem(['item_id' => 3, 'question' => 'Q']);

        $this->invoke($resource, '_beforeSave', $item);

        $this->assertFalse($item->hasData('_panth_scope_snapshot'));
        $this->assertSame([], $this->queries);
    }

    public function testAfterSaveRestoresSnapshotAndPersistsRelations(): void
    {
        $resource = $this->resource();
        $item = $this->newItem([
            'item_id' => 3,
            'question' => 'Default Q',
            '_panth_scope_snapshot' => ['question' => 'Store Q'],
            'stores' => [0, 1],
            'products' => [10],
            'catalog_categories' => [],
            'pages' => ['5'],
            'category_id' => ['2', '', 0, '4'],
        ]);

        $this->invoke($resource, '_afterSave', $item);

        $this->assertSame('Store Q', $item->getQuestion());
        $this->assertFalse($item->hasData('_panth_scope_snapshot'));
        $this->assertSame(
            [
                ['delete', 'pfx_panth_faq_item_store', ['item_id = ?' => 3]],
                ['insertMultiple', 'pfx_panth_faq_item_store', [
                    ['item_id' => 3, 'store_id' => 0],
                    ['item_id' => 3, 'store_id' => 1],
                ]],
                ['delete', 'pfx_panth_faq_item_product', ['item_id = ?' => 3]],
                ['insertMultiple', 'pfx_panth_faq_item_product', [
                    ['item_id' => 3, 'product_id' => 10, 'sort_order' => 0],
                ]],
                ['delete', 'pfx_panth_faq_item_catalog_category', ['item_id = ?' => 3]],
                ['delete', 'pfx_panth_faq_item_page', ['item_id = ?' => 3]],
                ['insertMultiple', 'pfx_panth_faq_item_page', [
                    ['item_id' => 3, 'page_id' => '5', 'sort_order' => 0],
                ]],
                ['delete', 'pfx_panth_faq_item_faq_category', ['item_id = ?' => 3]],
                ['insertMultiple', 'pfx_panth_faq_item_faq_category', [
                    ['item_id' => 3, 'faq_category_id' => '2', 'sort_order' => 0],
                    ['item_id' => 3, 'faq_category_id' => '4', 'sort_order' => 0],
                ]],
            ],
            $this->writes
        );
        $this->assertSame('panth_faq_item_save_after', $this->events[0][0]);
        $this->assertSame($item, $this->events[0][1]['item']);
    }

    public function testAfterSaveWithoutRelationDataLeavesTablesAlone(): void
    {
        $resource = $this->resource();

        $this->invoke($resource, '_afterSave', $this->newItem(['item_id' => 3]));

        $this->assertSame([], $this->writes);
        $this->assertCount(1, $this->events);
    }

    public function testAfterLoadHydratesRelations(): void
    {
        $resource = $this->resource();
        $item = $this->newItem(['item_id' => 3]);
        $this->colResults = [['0', '1'], ['10'], ['20', '21'], [], ['2']];

        $this->invoke($resource, '_afterLoad', $item);

        $this->assertSame(['0', '1'], $item->getData('stores'));
        $this->assertSame(['0', '1'], $item->getData('store_id'));
        $this->assertSame(['10'], $item->getData('products'));
        $this->assertSame(['20', '21'], $item->getData('catalog_categories'));
        $this->assertSame([], $item->getData('pages'));
        $this->assertSame(['2'], $item->getData('category_id'));
        $this->assertSame(['2'], $item->getData('faq_categories'));
    }

    public function testAfterLoadWithoutFaqCategoriesDoesNotSetAlias(): void
    {
        $resource = $this->resource();
        $item = $this->newItem(['item_id' => 3]);

        $this->invoke($resource, '_afterLoad', $item);

        $this->assertSame([], $item->getData('category_id'));
        $this->assertFalse($item->hasData('faq_categories'));
    }

    public function testAfterDeleteRemovesUrlRewrites(): void
    {
        $resource = $this->resource();

        $this->invoke($resource, '_afterDelete', $this->newItem(['item_id' => 3]));
        $this->invoke($resource, '_afterDelete', $this->newItem());

        $this->assertSame(
            [['delete', 'pfx_url_rewrite', ['entity_type = ?' => 'faq_item', 'entity_id = ?' => 3]]],
            $this->writes
        );
    }
}
