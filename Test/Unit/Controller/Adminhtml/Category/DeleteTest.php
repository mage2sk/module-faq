<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Controller\Adminhtml\Category;

use Magento\Framework\Exception\NoSuchEntityException;
use Panth\Faq\Api\CategoryRepositoryInterface;
use Panth\Faq\Controller\Adminhtml\Category\Delete;
use Panth\Faq\Test\Unit\Controller\ActionContextTrait;
use PHPUnit\Framework\TestCase;

class DeleteTest extends TestCase
{
    use ActionContextTrait;

    public function testDeletesCategory(): void
    {
        $repository = $this->createMock(CategoryRepositoryInterface::class);
        $repository->expects($this->once())->method('deleteById')->with('4')->willReturn(true);
        $this->requestParams = ['category_id' => '4'];

        (new Delete($this->actionContext(), $repository))->execute();

        $this->assertSame(['The FAQ category has been deleted.'], $this->messages['success']);
        $this->assertSame('*/*/', $this->redirect['path']);
    }

    public function testMissingIdSkipsDeletion(): void
    {
        $repository = $this->createMock(CategoryRepositoryInterface::class);
        $repository->expects($this->never())->method('deleteById');

        (new Delete($this->actionContext(), $repository))->execute();

        $this->assertSame([], $this->messages['success']);
        $this->assertSame([], $this->messages['error']);
    }

    public function testErrorsAreShown(): void
    {
        $repository = $this->createStub(CategoryRepositoryInterface::class);
        $repository->method('deleteById')->willThrowException(new NoSuchEntityException(__('No such category')));
        $this->requestParams = ['category_id' => '4'];

        (new Delete($this->actionContext(), $repository))->execute();

        $this->assertSame(['No such category'], $this->messages['error']);
        $this->assertSame('*/*/', $this->redirect['path']);
    }

    public function testAcl(): void
    {
        $this->assertSame('Panth_Faq::category_delete', Delete::ADMIN_RESOURCE);
        $controller = new Delete($this->actionContext(), $this->createStub(CategoryRepositoryInterface::class));
        $this->acl = ['Panth_Faq::category_delete'];
        $this->assertTrue($this->isAllowed($controller));
    }
}
