<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Controller\Adminhtml\Category;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Indexer\CacheContext;
use Panth\Faq\Controller\Adminhtml\Category\SaveFaq;
use Panth\Faq\Test\Unit\Controller\ActionContextTrait;
use PHPUnit\Framework\TestCase;

class SaveFaqTest extends TestCase
{
    use ActionContextTrait;

    protected array $json = [];
    protected array $db = [];
    protected array $events = [];
    protected array $cleaned = [];
    protected CacheContext $cacheContext;
    protected array $previousIds = [];
    protected bool $dbFails = false;

    protected function controllerClass(): string
    {
        return SaveFaq::class;
    }

    protected function idParam(): string
    {
        return 'category_id';
    }

    protected function table(): string
    {
        return 'panth_faq_item_catalog_category';
    }

    protected function tagPrefix(): string
    {
        return 'cat_c_';
    }

    protected function invalidMessage(): string
    {
        return 'Invalid category ID';
    }

    protected function controller(): object
    {
        $result = $this->createStub(Json::class);
        $result->method('setData')->willReturnCallback(function ($data) use ($result) {
            $this->json = array_map(static fn ($v) => is_object($v) ? (string)$v : $v, $data);
            return $result;
        });
        $jsonFactory = $this->createStub(JsonFactory::class);
        $jsonFactory->method('create')->willReturn($result);

        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnCallback(function ($table, $cols) use ($select) {
            $this->db['select_from'] = [$table, $cols];
            return $select;
        });
        $select->method('where')->willReturnCallback(function ($cond, $value = null) use ($select) {
            $this->db['select_where'] = [$cond, $value];
            return $select;
        });
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('getTableName')->willReturnCallback(fn ($name) => 'pfx_' . $name);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchCol')->willReturnCallback(function () {
            if ($this->dbFails) {
                throw new \RuntimeException('connection lost');
            }
            return $this->previousIds;
        });
        $connection->method('delete')->willReturnCallback(function ($table, $where) {
            $this->db['delete'] = [$table, $where];
            return 1;
        });
        $connection->method('insertMultiple')->willReturnCallback(function ($table, $rows) {
            $this->db['insert'] = [$table, $rows];
            return count($rows);
        });
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);

        $events = $this->createStub(EventManager::class);
        $events->method('dispatch')->willReturnCallback(function ($name, $data = []) {
            $this->events[] = [$name, $data];
        });
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('clean')->willReturnCallback(function ($tags = []) {
            $this->cleaned = $tags;
            return true;
        });
        $this->cacheContext = new CacheContext();
        $class = $this->controllerClass();

        return new $class(
            $this->actionContext(eventManager: $events),
            $jsonFactory,
            $resource,
            $this->cacheContext,
            $cache
        );
    }

    public function testRejectsMissingEntityId(): void
    {
        $this->controller()->execute();

        $this->assertSame(['success' => false, 'message' => $this->invalidMessage()], $this->json);
        $this->assertArrayNotHasKey('delete', $this->db);
    }

    public function testReplacesAssignmentsAndCleansCache(): void
    {
        $this->requestParams = [$this->idParam() => '10', 'faq_ids' => '4,5,abc,0'];
        $this->previousIds = ['3', '4'];

        $this->controller()->execute();

        $table = 'pfx_' . $this->table();
        $this->assertSame([$table, 'item_id'], $this->db['select_from']);
        $this->assertSame([$this->idParam() . ' = ?', 10], $this->db['select_where']);
        $this->assertSame([$table, [$this->idParam() . ' = ?' => 10]], $this->db['delete']);
        $this->assertSame(
            [$table, [['item_id' => 4, $this->idParam() => 10], ['item_id' => 5, $this->idParam() => 10]]],
            $this->db['insert']
        );
        $expectedTags = [$this->tagPrefix() . '10', 'panth_faq_item_3', 'panth_faq_item_4', 'panth_faq_item_5'];
        $this->assertSame($expectedTags, $this->cleaned);
        $this->assertSame($expectedTags, $this->cacheContext->getIdentities());
        $this->assertSame('clean_cache_by_tags', $this->events[0][0]);
        $this->assertSame($this->cacheContext, $this->events[0][1]['object']);
        $this->assertSame(['success' => true, 'message' => 'FAQ assignments saved successfully.'], $this->json);
    }

    public function testEmptySelectionOnlyRemovesExistingAssignments(): void
    {
        $this->requestParams = [$this->idParam() => '10', 'faq_ids' => ''];
        $this->previousIds = ['8'];

        $this->controller()->execute();

        $this->assertArrayHasKey('delete', $this->db);
        $this->assertArrayNotHasKey('insert', $this->db);
        $this->assertSame([$this->tagPrefix() . '10', 'panth_faq_item_8'], $this->cleaned);
        $this->assertTrue($this->json['success']);
    }

    public function testDatabaseErrorIsReturnedAsJson(): void
    {
        $this->requestParams = [$this->idParam() => '10', 'faq_ids' => '1'];
        $this->dbFails = true;

        $this->controller()->execute();

        $this->assertSame(
            ['success' => false, 'message' => 'Error saving FAQ assignments: connection lost'],
            $this->json
        );
        $this->assertSame([], $this->cleaned);
    }

    public function testRequiresItemSavePermission(): void
    {
        $controller = $this->controller();
        $this->acl = ['Panth_Faq::category_save'];
        $this->assertFalse($this->isAllowed($controller));
        $this->acl = ['Panth_Faq::item_save'];
        $this->assertTrue($this->isAllowed($controller));
    }
}
