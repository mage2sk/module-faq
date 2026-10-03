<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Block\Adminhtml\Item\Edit\Tab;

use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\DataObject;
use Magento\Framework\Registry;
use Panth\Faq\Block\Adminhtml\Item\Edit\Tab\CategoryTree;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CategoryTreeTest extends TestCase
{
    private $item = null;
    private array $categories = [];
    private bool $fail = false;
    private array $logged = [];

    private function block(): CategoryTree
    {
        $registry = $this->createStub(Registry::class);
        $registry->method('registry')->willReturnCallback(fn ($key) => $key === 'panth_faq_item' ? $this->item : null);
        $collection = $this->createStub(Collection::class);
        foreach (['addAttributeToSelect', 'addFieldToFilter', 'setOrder'] as $method) {
            $collection->method($method)->willReturnCallback(function () use ($collection) {
                if ($this->fail) {
                    throw new \RuntimeException('eav failure');
                }
                return $collection;
            });
        }
        $collection->method('getIterator')->willReturnCallback(fn () => new \ArrayIterator($this->categories));
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);
        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('critical')->willReturnCallback(function ($message) {
            $this->logged[] = $message;
        });

        $reflection = new \ReflectionClass(CategoryTree::class);
        $block = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('registry')->setValue($block, $registry);
        $reflection->getProperty('categoryCollectionFactory')->setValue($block, $factory);
        $reflection->getProperty('_logger')->setValue($block, $logger);

        return $block;
    }

    private function category(int $id, string $name, int $level, int $parent): DataObject
    {
        return new DataObject(['id' => $id, 'name' => $name, 'level' => $level, 'parent_id' => $parent]);
    }

    public static function selectedProvider(): array
    {
        return [
            'json string' => ['["3","7"]', [3, 7]],
            'comma string' => ['3,7', [3, 7]],
            'array' => [['4', 5], [4, 5]],
            'empty' => ['', []],
            'null' => [null, []],
            'unsupported type' => [12, []],
        ];
    }

    #[DataProvider('selectedProvider')]
    public function testSelectedCategories($value, array $expected): void
    {
        $this->item = new DataObject(['id' => 1, 'catalog_categories' => $value]);

        $this->assertSame($expected, $this->block()->getSelectedCategories());
    }

    public function testSelectedCategoriesRequireSavedItem(): void
    {
        $this->assertSame([], $this->block()->getSelectedCategories());

        $this->item = new DataObject(['id' => 0, 'catalog_categories' => [1]]);
        $this->assertSame([], $this->block()->getSelectedCategories());
    }

    public function testTreeJsonNestsChildrenUnderLevelTwoRoots(): void
    {
        $this->categories = [
            $this->category(3, 'Men', 2, 2),
            $this->category(6, 'Women', 2, 2),
            $this->category(4, 'Shirts', 3, 3),
            $this->category(9, 'Orphan', 3, 99),
            $this->category(5, 'Polo', 4, 4),
        ];

        $tree = json_decode($this->block()->getCategoryTreeJson(), true);

        $this->assertCount(2, $tree);
        $this->assertSame(['id' => 3, 'text' => 'Men', 'level' => 2, 'parent_id' => 2], array_diff_key($tree[0], ['children' => 1]));
        $this->assertSame('Shirts', $tree[0]['children'][0]['text']);
        $this->assertSame('Polo', $tree[0]['children'][0]['children'][0]['text']);
        $this->assertCount(1, $tree[0]['children']);
        $this->assertSame([], $tree[1]['children']);
    }

    public function testTreeJsonReturnsEmptyArrayOnFailure(): void
    {
        $this->fail = true;

        $this->assertSame('[]', $this->block()->getCategoryTreeJson());
        $this->assertSame('CategoryTree error: eav failure', $this->logged[0]);
        $this->assertCount(2, $this->logged);
    }

    public function testFieldName(): void
    {
        $this->assertSame('catalog_categories', $this->block()->getFieldName());
    }
}
