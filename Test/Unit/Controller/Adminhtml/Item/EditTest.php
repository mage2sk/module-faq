<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Controller\Adminhtml\Item;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Panth\Faq\Api\ItemRepositoryInterface;
use Panth\Faq\Controller\Adminhtml\Item\Edit;
use Panth\Faq\Logger\Logger;
use Panth\Faq\Model\ItemFactory;
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

    private function controller(ItemRepositoryInterface $repository, ?Logger $logger = null): Edit
    {
        $registry = $this->createStub(Registry::class);
        $registry->method('register')->willReturnCallback(function ($key, $value) {
            $this->registered[$key] = $value;
        });
        $factory = $this->createStub(ItemFactory::class);
        $factory->method('create')->willReturnCallback(fn () => $this->newItem());

        return new Edit(
            $this->actionContext(),
            $this->pageFactory(),
            $repository,
            $registry,
            $logger ?? $this->createStub(Logger::class),
            $factory
        );
    }

    public function testNewItemRegistersEmptyModel(): void
    {
        $result = $this->controller($this->createStub(ItemRepositoryInterface::class))->execute();

        $this->assertSame($this->pageCalls['page'], $result);
        $this->assertNull($this->registered['panth_faq_item']->getId());
        $this->assertSame('New FAQ Item', $this->pageCalls['title']);
        $this->assertSame('Panth_Faq::item', $this->pageCalls['menu']);
    }

    public function testExistingItemIsLoadedAndRegistered(): void
    {
        $item = $this->newItem(['item_id' => 5]);
        $repository = $this->createMock(ItemRepositoryInterface::class);
        $repository->expects($this->once())->method('getById')->with('5')->willReturn($item);
        $this->requestParams = ['item_id' => '5'];

        $this->controller($repository)->execute();

        $this->assertSame($item, $this->registered['panth_faq_item']);
        $this->assertSame('Edit FAQ Item', $this->pageCalls['title']);
    }

    public function testMissingItemRedirectsToGridWithError(): void
    {
        $repository = $this->createStub(ItemRepositoryInterface::class);
        $repository->method('getById')->willThrowException(new NoSuchEntityException(__('nope')));
        $logger = $this->createMock(Logger::class);
        $logger->expects($this->once())->method('error')->with('Failed to load item: nope');
        $this->requestParams = ['item_id' => '77'];

        $this->controller($repository, $logger)->execute();

        $this->assertSame('*/*/', $this->redirect['path']);
        $this->assertSame(['This FAQ item no longer exists.'], $this->messages['error']);
        $this->assertSame([], $this->registered);
        $this->assertSame('Panth_Faq::item_save', Edit::ADMIN_RESOURCE);
    }
}
