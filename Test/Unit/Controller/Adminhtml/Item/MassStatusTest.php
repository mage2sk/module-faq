<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Controller\Adminhtml\Item;

use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;
use Panth\Faq\Api\ItemRepositoryInterface;
use Panth\Faq\Controller\Adminhtml\Item\MassStatus;
use Panth\Faq\Model\ResourceModel\Item\Collection;
use Panth\Faq\Model\ResourceModel\Item\CollectionFactory;
use Panth\Faq\Test\Unit\Controller\ActionContextTrait;
use Panth\Faq\Test\Unit\EntityTrait;
use PHPUnit\Framework\TestCase;

class MassStatusTest extends TestCase
{
    use ActionContextTrait;
    use EntityTrait;

    private array $saved = [];

    private function controller(array $ids, array $failingIds = [], bool $filterFails = false): MassStatus
    {
        $collection = $this->createStub(Collection::class);
        $collection->method('getAllIds')->willReturn($ids);
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);
        $filter = $this->createStub(Filter::class);
        if ($filterFails) {
            $filter->method('getCollection')->willThrowException(new LocalizedException(__('No selection')));
        } else {
            $filter->method('getCollection')->willReturn($collection);
        }
        $repository = $this->createStub(ItemRepositoryInterface::class);
        $repository->method('getById')->willReturnCallback(function ($id) use ($failingIds) {
            if (in_array($id, $failingIds, true)) {
                throw new \RuntimeException('missing');
            }
            return $this->newItem(['item_id' => $id, 'is_active' => 'unchanged']);
        });
        $repository->method('save')->willReturnCallback(function ($item) {
            $this->saved[$item->getId()] = $item->getIsActive();
            return $item;
        });

        return new MassStatus($this->actionContext(), $filter, $factory, $repository);
    }

    public function testRejectsUnknownType(): void
    {
        $this->requestParams = ['type' => 'toggle'];

        $this->controller(['1'])->execute();

        $this->assertSame(['Invalid status action. Expected "enable" or "disable".'], $this->messages['error']);
        $this->assertSame([], $this->saved);
        $this->assertSame('*/*/', $this->redirect['path']);
    }

    public function testEnablesSelectedItems(): void
    {
        $this->requestParams = ['type' => 'enable'];

        $this->controller(['4', '5'])->execute();

        $this->assertSame([4 => 1, 5 => 1], $this->saved);
        $this->assertSame(['A total of 2 FAQ item(s) have been enabled.'], $this->messages['success']);
        $this->assertSame([], $this->messages['warning']);
    }

    public function testDisableReportsPartialFailures(): void
    {
        $this->requestParams = ['type' => 'disable'];

        $this->controller(['4', '5'], [5])->execute();

        $this->assertSame([4 => 0], $this->saved);
        $this->assertSame(['FAQ item 5: missing'], $this->messages['error']);
        $this->assertSame(['A total of 1 FAQ item(s) have been disabled.'], $this->messages['success']);
        $this->assertSame([], $this->messages['warning']);
    }

    public function testWarnsWhenNothingWasUpdated(): void
    {
        $this->requestParams = ['type' => 'enable'];

        $this->controller(['9'], [9])->execute();

        $this->assertSame([], $this->messages['success']);
        $this->assertSame(['No FAQ items were updated. See errors above.'], $this->messages['warning']);
    }

    public function testFilterErrorIsReported(): void
    {
        $this->requestParams = ['type' => 'enable'];

        $this->controller([], [], true)->execute();

        $this->assertSame(['No selection'], $this->messages['error']);
        $this->assertSame('*/*/', $this->redirect['path']);
        $this->assertSame('Panth_Faq::item_save', MassStatus::ADMIN_RESOURCE);
    }
}
