<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit;

use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Panth\Faq\Model\Category;
use Panth\Faq\Model\Item;
use Panth\Faq\Model\ResourceModel\Category as CategoryResource;
use Panth\Faq\Model\ResourceModel\Item as ItemResource;

trait EntityTrait
{
    protected function newItem(array $data = []): Item
    {
        $resource = $this->createStub(ItemResource::class);
        $resource->method('getIdFieldName')->willReturn('item_id');

        return new Item($this->modelContext(), $this->createStub(Registry::class), $resource, null, $data);
    }

    protected function newCategory(array $data = []): Category
    {
        $resource = $this->createStub(CategoryResource::class);
        $resource->method('getIdFieldName')->willReturn('category_id');

        return new Category($this->modelContext(), $this->createStub(Registry::class), $resource, null, $data);
    }

    private function modelContext(): Context
    {
        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(ManagerInterface::class));

        return $context;
    }
}
