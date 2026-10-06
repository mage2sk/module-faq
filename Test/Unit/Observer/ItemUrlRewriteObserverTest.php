<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Observer;

use Magento\Framework\DataObject;
use Panth\Faq\Model\ResourceModel\Item as ItemResource;
use Panth\Faq\Observer\ItemUrlRewriteObserver;

class ItemUrlRewriteObserverTest extends CategoryUrlRewriteObserverTest
{
    protected function eventKey(): string
    {
        return 'item';
    }

    protected function entityType(): string
    {
        return 'faq_item';
    }

    protected function pathSegment(): string
    {
        return 'item';
    }

    protected function targetPath(int $id): string
    {
        return 'faq/index/view/id/' . $id;
    }

    protected function resourceClass(): string
    {
        return ItemResource::class;
    }

    protected function buildObserver(...$args): object
    {
        return new ItemUrlRewriteObserver($args[0], $args[1], $args[2], $args[4], $args[3], $args[5]);
    }

    public function testUnexpectedErrorsAreLogged(): void
    {
        $entity = $this->createStub(DataObject::class);
        $entity->method('__call')->willThrowException(new \RuntimeException('bad entity'));

        $this->dispatch($entity);

        $this->assertSame([], $this->created);
        $this->assertSame(['FAQ Item URL Rewrite Error: bad entity'], $this->logged);
    }
}
