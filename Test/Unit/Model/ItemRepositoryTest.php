<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\Faq\Api\Data\ItemSearchResultsInterface;
use Panth\Faq\Api\Data\ItemSearchResultsInterfaceFactory;
use Panth\Faq\Model\ItemFactory;
use Panth\Faq\Model\ItemRepository;
use Panth\Faq\Model\ResourceModel\Item as ItemResource;
use Panth\Faq\Model\ResourceModel\Item\Collection;
use Panth\Faq\Model\ResourceModel\Item\CollectionFactory;
use Panth\Faq\Test\Unit\EntityTrait;
use PHPUnit\Framework\TestCase;

class ItemRepositoryTest extends TestCase
{
    use EntityTrait;

    private ItemResource $resource;
    private CollectionFactory $collectionFactory;
    private ItemSearchResultsInterfaceFactory $resultsFactory;
    private CollectionProcessorInterface $processor;

    protected function setUp(): void
    {
        $this->resource = $this->createStub(ItemResource::class);
        $this->collectionFactory = $this->createStub(CollectionFactory::class);
        $this->resultsFactory = $this->createStub(ItemSearchResultsInterfaceFactory::class);
        $this->processor = $this->createStub(CollectionProcessorInterface::class);
    }

    private function repository(): ItemRepository
    {
        $factory = $this->createStub(ItemFactory::class);
        $factory->method('create')->willReturnCallback(fn () => $this->newItem());

        return new ItemRepository(
            $this->resource,
            $factory,
            $this->collectionFactory,
            $this->resultsFactory,
            $this->processor
        );
    }

    public function testSaveReturnsItem(): void
    {
        $item = $this->newItem(['item_id' => 1]);
        $resource = $this->createMock(ItemResource::class);
        $resource->expects($this->once())->method('save')->with($item);
        $this->resource = $resource;

        $this->assertSame($item, $this->repository()->save($item));
    }

    public function testSaveWrapsErrors(): void
    {
        $this->resource->method('save')->willThrowException(new \RuntimeException('Duplicate entry'));

        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage('Duplicate entry');
        $this->repository()->save($this->newItem());
    }

    public function testGetByIdLoadsItem(): void
    {
        $this->resource->method('load')->willReturnCallback(function ($item, $id) {
            $item->setData(['item_id' => $id, 'question' => 'Loaded']);
            return $this->resource;
        });

        $item = $this->repository()->getById(4);

        $this->assertSame(4, $item->getId());
        $this->assertSame('Loaded', $item->getQuestion());
    }

    public function testGetByIdThrowsWhenMissing(): void
    {
        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('FAQ item with ID "9" does not exist.');
        $this->repository()->getById(9);
    }

    public function testGetListAppliesCriteria(): void
    {
        $criteria = $this->createStub(SearchCriteriaInterface::class);
        $items = [$this->newItem(['item_id' => 1])];
        $collection = $this->createStub(Collection::class);
        $collection->method('getItems')->willReturn($items);
        $collection->method('getSize')->willReturn(11);
        $this->collectionFactory->method('create')->willReturn($collection);
        $processor = $this->createMock(CollectionProcessorInterface::class);
        $processor->expects($this->once())->method('process')->with($criteria, $collection);
        $this->processor = $processor;
        $results = $this->createMock(ItemSearchResultsInterface::class);
        $results->expects($this->once())->method('setSearchCriteria')->with($criteria);
        $results->expects($this->once())->method('setItems')->with($items);
        $results->expects($this->once())->method('setTotalCount')->with(11);
        $this->resultsFactory->method('create')->willReturn($results);

        $this->assertSame($results, $this->repository()->getList($criteria));
    }

    public function testDeleteAndDeleteById(): void
    {
        $deleted = [];
        $this->resource->method('load')->willReturnCallback(function ($item, $id) {
            $item->setId($id);
            return $this->resource;
        });
        $this->resource->method('delete')->willReturnCallback(function ($item) use (&$deleted) {
            $deleted[] = $item->getId();
            return $this->resource;
        });

        $this->assertTrue($this->repository()->delete($this->newItem(['item_id' => 2])));
        $this->assertTrue($this->repository()->deleteById(3));
        $this->assertSame([2, 3], $deleted);
    }

    public function testDeleteWrapsErrors(): void
    {
        $this->resource->method('delete')->willThrowException(new \RuntimeException('FK constraint'));

        $this->expectException(CouldNotDeleteException::class);
        $this->expectExceptionMessage('FK constraint');
        $this->repository()->delete($this->newItem(['item_id' => 2]));
    }
}
