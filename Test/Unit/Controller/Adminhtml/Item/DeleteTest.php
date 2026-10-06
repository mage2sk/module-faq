<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Controller\Adminhtml\Item;

use Magento\Framework\Exception\CouldNotDeleteException;
use Panth\Faq\Api\ItemRepositoryInterface;
use Panth\Faq\Controller\Adminhtml\Item\Delete;
use Panth\Faq\Test\Unit\Controller\ActionContextTrait;
use PHPUnit\Framework\TestCase;

class DeleteTest extends TestCase
{
    use ActionContextTrait;

    public function testDeletesItemById(): void
    {
        $repository = $this->createMock(ItemRepositoryInterface::class);
        $repository->expects($this->once())->method('deleteById')->with('12');
        $this->requestParams = ['item_id' => '12'];

        (new Delete($this->actionContext(), $repository))->execute();

        $this->assertSame(['The FAQ item has been deleted.'], $this->messages['success']);
        $this->assertSame('*/*/', $this->redirect['path']);
    }

    public function testWithoutIdNothingIsDeleted(): void
    {
        $repository = $this->createMock(ItemRepositoryInterface::class);
        $repository->expects($this->never())->method('deleteById');

        (new Delete($this->actionContext(), $repository))->execute();

        $this->assertSame([], $this->messages['success']);
        $this->assertSame('*/*/', $this->redirect['path']);
    }

    public function testDeleteFailureIsReported(): void
    {
        $repository = $this->createStub(ItemRepositoryInterface::class);
        $repository->method('deleteById')->willThrowException(new CouldNotDeleteException(__('locked')));
        $this->requestParams = ['item_id' => '3'];

        (new Delete($this->actionContext(), $repository))->execute();

        $this->assertSame(['locked'], $this->messages['error']);
        $this->assertSame('*/*/', $this->redirect['path']);
    }

    public function testAcl(): void
    {
        $this->assertSame('Panth_Faq::item_delete', Delete::ADMIN_RESOURCE);
        $controller = new Delete($this->actionContext(), $this->createStub(ItemRepositoryInterface::class));
        $this->acl = ['Panth_Faq::item_save'];
        $this->assertFalse($this->isAllowed($controller));
        $this->acl[] = 'Panth_Faq::item_delete';
        $this->assertTrue($this->isAllowed($controller));
    }
}
