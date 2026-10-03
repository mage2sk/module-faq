<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\Faq\Api\Data\CategorySearchResultsInterface;
use Panth\Faq\Api\Data\CategorySearchResultsInterfaceFactory;
use Panth\Faq\Model\CategoryFactory;
use Panth\Faq\Model\CategoryRepository;
use Panth\Faq\Model\ResourceModel\Category as CategoryResource;
use Panth\Faq\Model\ResourceModel\Category\Collection;
use Panth\Faq\Model\ResourceModel\Category\CollectionFactory;
use Panth\Faq\Test\Unit\EntityTrait;
use PHPUnit\Framework\TestCase;

class CategoryRepositoryTest extends TestCase
{
    use EntityTrait;

    private CategoryResource $resource;
    private CollectionFactory $collectionFactory;
    private CategorySearchResultsInterfaceFactory $resultsFactory;
    private CollectionProcessorInterface $processor;

    protected function setUp(): void
    {
        $this->resource = $this->createStub(CategoryResource::class);
        $this->collectionFactory = $this->createStub(CollectionFactory::class);
        $this->resultsFactory = $this->createStub(CategorySearchResultsInterfaceFactory::class);
        $this->processor = $this->createStub(CollectionProcessorInterface::class);
    }

    private function repository(): CategoryRepository
    {
        $factory = $this->createStub(CategoryFactory::class);
        $factory->method('create')->willReturnCallback(fn () => $this->newCategory());

        return new CategoryRepository(
            $this->resource,
            $factory,
            $this->collectionFactory,
            $this->resultsFactory,
            $this->processor
        );
    }

    public function testSaveReturnsCategoryAndWrapsErrors(): void
    {
        $category = $this->newCategory(['category_id' => 1]);
        $this->assertSame($category, $this->repository()->save($category));

        $this->resource->method('save')->willThrowException(new \RuntimeException('URL key exists'));
        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage('URL key exists');
        $this->repository()->save($category);
    }

    public function testGetById(): void
    {
        $this->resource->method('load')->willReturnCallback(function ($category, $id) {
            if ($id === 5) {
                $category->setData(['category_id' => 5, 'name' => 'Returns']);
            }
            return $this->resource;
        });

        $this->assertSame('Returns', $this->repository()->getById(5)->getName());

        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('FAQ category with ID "6" does not exist.');
        $this->repository()->getById(6);
    }

    public function testGetList(): void
    {
        $criteria = $this->createStub(SearchCriteriaInterface::class);
        $collection = $this->createStub(Collection::class);
        $collection->method('getItems')->willReturn([]);
        $collection->method('getSize')->willReturn(0);
        $this->collectionFactory->method('create')->willReturn($collection);
        $results = $this->createMock(CategorySearchResultsInterface::class);
        $results->expects($this->once())->method('setSearchCriteria')->with($criteria);
        $results->expects($this->once())->method('setItems')->with([]);
        $results->expects($this->once())->method('setTotalCount')->with(0);
        $this->resultsFactory->method('create')->willReturn($results);

        $this->assertSame($results, $this->repository()->getList($criteria));
    }

    public function testDeleteById(): void
    {
        $this->resource->method('load')->willReturnCallback(function ($category, $id) {
            $category->setId($id);
            return $this->resource;
        });
        $resourceDeletes = [];
        $this->resource->method('delete')->willReturnCallback(function ($category) use (&$resourceDeletes) {
            $resourceDeletes[] = $category->getId();
            return $this->resource;
        });

        $this->assertTrue($this->repository()->deleteById(8));
        $this->assertSame([8], $resourceDeletes);
    }

    public function testDeleteWrapsErrors(): void
    {
        $this->resource->method('delete')->willThrowException(new \RuntimeException('in use'));

        $this->expectException(CouldNotDeleteException::class);
        $this->expectExceptionMessage('in use');
        $this->repository()->delete($this->newCategory(['category_id' => 1]));
    }
}
