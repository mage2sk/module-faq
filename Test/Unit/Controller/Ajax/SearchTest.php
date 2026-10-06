<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Controller\Ajax;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\DB\Select;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Faq\Controller\Ajax\Search;
use Panth\Faq\Helper\Data as FaqHelper;
use Panth\Faq\Model\ResourceModel\Item\Collection;
use Panth\Faq\Model\ResourceModel\Item\CollectionFactory;
use Panth\Faq\Test\Unit\EntityTrait;
use PHPUnit\Framework\TestCase;

class SearchTest extends TestCase
{
    use EntityTrait;

    private array $params = [];
    private array $json = [];
    private array $calls = [];
    private array $items = [];
    private bool $fail = false;

    private function controller(): Search
    {
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturnCallback(
            fn ($key, $default = null) => $this->params[$key] ?? $default
        );
        $result = $this->createStub(Json::class);
        $result->method('setData')->willReturnCallback(function ($data) use ($result) {
            $this->json = array_map(static fn ($v) => is_object($v) ? (string)$v : $v, $data);
            return $result;
        });
        $jsonFactory = $this->createStub(JsonFactory::class);
        $jsonFactory->method('create')->willReturn($result);

        $select = $this->createStub(Select::class);
        $select->method('join')->willReturnCallback(function ($name, $cond) use ($select) {
            $this->calls['join'] = [$name, $cond];
            return $select;
        });
        $select->method('where')->willReturnCallback(function ($cond, $value = null) use ($select) {
            $this->calls['where'] = [$cond, $value];
            return $select;
        });
        $collection = $this->createStub(Collection::class);
        foreach (['addActiveFilter', 'addStoreFilter', 'addSearchFilter', 'setOrder', 'setPageSize'] as $method) {
            $collection->method($method)->willReturnCallback(function (...$args) use ($method, $collection) {
                if ($this->fail) {
                    throw new \RuntimeException('sql error');
                }
                $this->calls[$method] = $args;
                return $collection;
            });
        }
        $collection->method('getSelect')->willReturn($select);
        $collection->method('getTable')->willReturnCallback(fn ($t) => 'pfx_' . $t);
        $collection->method('getIterator')->willReturnCallback(fn () => new \ArrayIterator($this->items));
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(3);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $helper = $this->createStub(FaqHelper::class);
        $helper->method('renderRichText')->willReturnCallback(fn ($text) => '<div>' . $text . '</div>');

        return new Search($request, $jsonFactory, $factory, $storeManager, $helper);
    }

    public function testShortQueryIsRejected(): void
    {
        $this->params = ['q' => ' a '];

        $this->controller()->execute();

        $this->assertSame(
            ['success' => false, 'message' => 'Search query must be at least 2 characters long.', 'results' => []],
            $this->json
        );
        $this->assertArrayNotHasKey('addSearchFilter', $this->calls);
    }

    public function testNonStringQueryIsTreatedAsEmpty(): void
    {
        $this->params = ['q' => ['injection']];

        $this->controller()->execute();

        $this->assertFalse($this->json['success']);
    }

    public function testSearchReturnsRenderedResults(): void
    {
        $this->params = ['q' => '  shipping  '];
        $this->items = [$this->newItem([
            'item_id' => 5,
            'question' => 'Shipping?',
            'answer' => 'Two days',
            'url_key' => 'shipping',
            'view_count' => 10,
            'helpful_count' => 4,
            'not_helpful_count' => 1,
        ])];

        $this->controller()->execute();

        $this->assertSame([3, true], $this->calls['addStoreFilter']);
        $this->assertSame(['shipping'], $this->calls['addSearchFilter']);
        $this->assertSame(['sort_order', 'ASC'], $this->calls['setOrder']);
        $this->assertSame([50], $this->calls['setPageSize']);
        $this->assertArrayNotHasKey('join', $this->calls);
        $this->assertSame(
            [
                'success' => true,
                'query' => 'shipping',
                'count' => 1,
                'results' => [[
                    'id' => 5,
                    'question' => 'Shipping?',
                    'answer' => '<div>Two days</div>',
                    'url_key' => 'shipping',
                    'view_count' => 10,
                    'helpful_count' => 4,
                    'not_helpful_count' => 1,
                ]],
            ],
            $this->json
        );
    }

    public function testQueryIsTruncatedAndCategoryFilterJoined(): void
    {
        $this->params = ['q' => str_repeat("\u{00e4}", 250), 'category' => '6'];

        $this->controller()->execute();

        $this->assertSame(200, mb_strlen($this->calls['addSearchFilter'][0], 'UTF-8'));
        $this->assertSame(['faq_cat' => 'pfx_panth_faq_item_faq_category'], $this->calls['join'][0]);
        $this->assertSame(['faq_cat.faq_category_id = ?', 6], $this->calls['where']);
        $this->assertSame(0, $this->json['count']);
    }

    public function testErrorsReturnGenericFailure(): void
    {
        $this->params = ['q' => 'returns'];
        $this->fail = true;

        $this->controller()->execute();

        $this->assertSame(
            ['success' => false, 'message' => 'An error occurred while searching.', 'results' => []],
            $this->json
        );
    }
}
