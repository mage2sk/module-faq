<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Controller\Adminhtml\Category;

use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Ui\Component\MassAction\Filter;
use Panth\Faq\Api\CategoryRepositoryInterface;
use Panth\Faq\Controller\Adminhtml\Category\MassDelete;
use Panth\Faq\Model\ResourceModel\Category\Collection;
use Panth\Faq\Model\ResourceModel\Category\CollectionFactory;
use Panth\Faq\Test\Unit\Controller\ActionContextTrait;
use Panth\Faq\Test\Unit\EntityTrait;
use PHPUnit\Framework\TestCase;

class MassDeleteTest extends TestCase
{
    use ActionContextTrait;
    use EntityTrait;

    public function testDeletesFilteredCategories(): void
    {
        $collection = $this->createStub(Collection::class);
        $collection->method('getSize')->willReturn(3);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([
            $this->newCategory(['category_id' => 7]),
            $this->newCategory(['category_id' => 8]),
            $this->newCategory(['category_id' => 9]),
        ]));
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);
        $filter = $this->createStub(Filter::class);
        $filter->method('getCollection')->willReturn($collection);
        $deleted = [];
        $repository = $this->createStub(CategoryRepositoryInterface::class);
        $repository->method('delete')->willReturnCallback(function ($category) use (&$deleted) {
            $deleted[] = $category->getId();
            return true;
        });
        $redirect = $this->createMock(Redirect::class);
        $redirect->expects($this->once())->method('setPath')->with('*/*/')->willReturnSelf();
        $resultFactory = $this->createStub(ResultFactory::class);
        $resultFactory->method('create')->willReturn($redirect);

        $controller = new MassDelete($this->actionContext(resultFactory: $resultFactory), $filter, $factory, $repository);

        $this->assertSame($redirect, $controller->execute());
        $this->assertSame([7, 8, 9], $deleted);
        $this->assertSame(['A total of 3 record(s) have been deleted.'], $this->messages['success']);
        $this->assertSame('Panth_Faq::category_delete', MassDelete::ADMIN_RESOURCE);
    }
}
