<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Controller\Adminhtml\Category;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Panth\Faq\Api\CategoryRepositoryInterface;
use Panth\Faq\Controller\Adminhtml\Category\Edit;
use Panth\Faq\Logger\Logger;
use Panth\Faq\Test\Unit\Controller\ActionContextTrait;
use Panth\Faq\Test\Unit\Controller\PageResultTrait;
use Panth\Faq\Test\Unit\EntityTrait;
use PHPUnit\Framework\TestCase;

class EditTest extends TestCase
{
    use ActionContextTrait;
    use EntityTrait;
    use PageResultTrait;

    private array $registered = [];

    private function controller(CategoryRepositoryInterface $repository, ?Logger $logger = null): Edit
    {
        $registry = $this->createStub(Registry::class);
        $registry->method('register')->willReturnCallback(function ($key, $value) {
            $this->registered[$key] = $value;
        });

        return new Edit(
            $this->actionContext(),
            $this->pageFactory(),
            $repository,
            $registry,
            $logger ?? $this->createStub(Logger::class)
        );
    }

    public function testNewCategoryRegistersNull(): void
    {
        $result = $this->controller($this->createStub(CategoryRepositoryInterface::class))->execute();

        $this->assertSame($this->pageCalls['page'], $result);
        $this->assertArrayHasKey('panth_faq_category', $this->registered);
        $this->assertNull($this->registered['panth_faq_category']);
        $this->assertSame('New FAQ Category', $this->pageCalls['title']);
        $this->assertSame('Panth_Faq::category', $this->pageCalls['menu']);
    }

    public function testExistingCategoryIsRegistered(): void
    {
        $category = $this->newCategory(['category_id' => 2]);
        $repository = $this->createStub(CategoryRepositoryInterface::class);
        $repository->method('getById')->willReturn($category);
        $this->requestParams = ['category_id' => '2'];

        $this->controller($repository)->execute();

        $this->assertSame($category, $this->registered['panth_faq_category']);
        $this->assertSame('Edit FAQ Category', $this->pageCalls['title']);
    }

    public function testMissingCategoryRedirects(): void
    {
        $repository = $this->createStub(CategoryRepositoryInterface::class);
        $repository->method('getById')->willThrowException(new NoSuchEntityException(__('gone')));
        $logger = $this->createMock(Logger::class);
        $logger->expects($this->once())->method('error')->with('Failed to load category: gone');
        $this->requestParams = ['category_id' => '2'];

        $this->controller($repository, $logger)->execute();

        $this->assertSame(['This FAQ category no longer exists.'], $this->messages['error']);
        $this->assertSame('*/*/', $this->redirect['path']);
        $this->assertSame([], $this->registered);
        $this->assertSame('Panth_Faq::category_save', Edit::ADMIN_RESOURCE);
    }
}
