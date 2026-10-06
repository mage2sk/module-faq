<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Controller\Adminhtml\Item;

use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;
use Panth\Faq\Api\ItemRepositoryInterface;
use Panth\Faq\Controller\Adminhtml\Item\MassShowOnMain;
use Panth\Faq\Model\ResourceModel\Item\Collection;
use Panth\Faq\Model\ResourceModel\Item\CollectionFactory;
use Panth\Faq\Test\Unit\Controller\ActionContextTrait;
use Panth\Faq\Test\Unit\EntityTrait;
use PHPUnit\Framework\TestCase;

class MassShowOnMainTest extends TestCase
{
    use ActionContextTrait;
    use EntityTrait;

    protected array $saved = [];

    protected function controllerClass(): string
    {
        return MassShowOnMain::class;
    }

    protected function expectedFlag(): int
    {
        return 1;
    }

    protected function expectedSuffix(): string
    {
        return 'have been set to show on main page.';
    }

    protected function controller(array $ids, array $failingIds = [], bool $filterFails = false): object
    {
        $collection = $this->createStub(Collection::class);
        $collection->method('getAllIds')->willReturn($ids);
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);
        $filter = $this->createStub(Filter::class);
        if ($filterFails) {
            $filter->method('getCollection')->willThrowException(new LocalizedException(__('Pick something')));
        } else {
            $filter->method('getCollection')->willReturn($collection);
        }
        $repository = $this->createStub(ItemRepositoryInterface::class);
        $repository->method('getById')->willReturnCallback(function ($id) use ($failingIds) {
            if (in_array($id, $failingIds, true)) {
                throw new \RuntimeException('cannot load');
            }
            return $this->newItem(['item_id' => $id, 'show_on_main' => 'unchanged']);
        });
        $repository->method('save')->willReturnCallback(function ($item) {
            $this->saved[$item->getId()] = $item->getShowOnMain();
            return $item;
        });
        $class = $this->controllerClass();

        return new $class($this->actionContext(), $filter, $factory, $repository);
    }

    public function testUpdatesEverySelectedItem(): void
    {
        $this->controller(['2', '3'])->execute();

        $flag = $this->expectedFlag();
        $this->assertSame([2 => $flag, 3 => $flag], $this->saved);
        $this->assertSame(['A total of 2 FAQ item(s) ' . $this->expectedSuffix()], $this->messages['success']);
        $this->assertSame('*/*/', $this->redirect['path']);
    }

    public function testPartialFailureKeepsSuccessMessage(): void
    {
        $this->controller(['2', '3'], [2])->execute();

        $this->assertSame([3 => $this->expectedFlag()], $this->saved);
        $this->assertSame(['FAQ item 2: cannot load'], $this->messages['error']);
        $this->assertSame(['A total of 1 FAQ item(s) ' . $this->expectedSuffix()], $this->messages['success']);
        $this->assertSame([], $this->messages['warning']);
    }

    public function testAllFailuresProduceWarning(): void
    {
        $this->controller(['2'], [2])->execute();

        $this->assertSame([], $this->messages['success']);
        $this->assertSame(['No FAQ items were updated. See errors above.'], $this->messages['warning']);
    }

    public function testFilterErrorStopsEarly(): void
    {
        $this->controller([], [], true)->execute();

        $this->assertSame(['Pick something'], $this->messages['error']);
        $this->assertSame([], $this->saved);
        $class = $this->controllerClass();
        $this->assertSame('Panth_Faq::item_save', $class::ADMIN_RESOURCE);
    }
}
