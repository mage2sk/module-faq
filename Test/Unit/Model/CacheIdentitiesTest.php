<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Model;

use Magento\Framework\DataObject;
use Panth\Faq\Model\CacheIdentities;
use Panth\Faq\Model\Category;
use Panth\Faq\Model\Item;
use PHPUnit\Framework\TestCase;

class CacheIdentitiesTest extends TestCase
{
    private function entity($id): object
    {
        return new class ($id) {
            public function __construct(private $id)
            {
            }

            public function getId()
            {
                return $this->id;
            }
        };
    }

    public function testListingReturnsBothEntityTags(): void
    {
        $this->assertSame([Item::CACHE_TAG, Category::CACHE_TAG], CacheIdentities::listing());
    }

    public function testFromItemsCollectsPositiveIdsAndDeduplicates(): void
    {
        $first = [$this->entity(3), $this->entity(5)];
        $second = new \ArrayIterator([$this->entity(5), $this->entity(0), $this->entity(null)]);

        $result = CacheIdentities::fromItems([$first, $second], ['cat_p_9']);

        $this->assertSame(['cat_p_9', 'panth_faq_item_3', 'panth_faq_item_5'], $result);
    }

    public function testFromItemsSkipsNonIterableSourcesAndObjectsWithoutGetIdMethod(): void
    {
        $result = CacheIdentities::fromItems(
            [null, 'string', [new \stdClass(), 'scalar', new DataObject(['id' => 4]), $this->entity('12')]]
        );

        $this->assertSame(['panth_faq_item_12'], $result);
    }

    public function testFromItemsReturnsOnlyExtraWhenNoItems(): void
    {
        $this->assertSame(['a', 'b'], CacheIdentities::fromItems([], ['a', 'b', 'a']));
    }
}
