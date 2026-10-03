<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Controller\Adminhtml\Item;

use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Ui\Component\MassAction\Filter;
use Panth\Faq\Api\ItemRepositoryInterface;
use Panth\Faq\Controller\Adminhtml\Item\MassDelete;
use Panth\Faq\Model\ResourceModel\Item\Collection;
use Panth\Faq\Model\ResourceModel\Item\CollectionFactory;
use Panth\Faq\Test\Unit\Controller\ActionContextTrait;
use Panth\Faq\Test\Unit\EntityTrait;
use PHPUnit\Framework\TestCase;

class MassDeleteTest extends TestCase
{
    use ActionContextTrait;
    use EntityTrait;

    public function testDeletesEverySelectedItem(): void
    {
        $items = [$this->newItem(['item_id' => 1]), $this->newItem(['item_id' => 2])];
        $collection = $this->createStub(Collection::class);
        $collection->method('getSize')->willReturn(2);
        $collection->method('getIterator')->willReturn(new \ArrayIterator($items));
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);
        $filter = $this->createMock(Filter::class);
        $filter->expects($this->once())->method('getCollection')->with($collection)->willReturn($collection);

        $deleted = [];
        $repository = $this->createStub(ItemRepositoryInterface::class);
        $repository->method('delete')->willReturnCallback(function ($item) use (&$deleted) {
            $deleted[] = $item->getId();
            return true;
        });

        $redirect = $this->createMock(Redirect::class);
        $redirect->expects($this->once())->method('setPath')->with('*/*/')->willReturnSelf();
        $resultFactory = $this->createStub(ResultFactory::class);
        $resultFactory->method('create')->willReturn($redirect);

        $controller = new MassDelete(
            $this->actionContext(resultFactory: $resultFactory),
            $filter,
            $factory,
            $repository
        );

        $this->assertSame($redirect, $controller->execute());
        $this->assertSame([1, 2], $deleted);
        $this->assertSame(['A total of 2 record(s) have been deleted.'], $this->messages['success']);
        $this->assertSame('Panth_Faq::item_delete', MassDelete::ADMIN_RESOURCE);
    }
}
