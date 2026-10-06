<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Block\Adminhtml\Item\Edit;

use Panth\Faq\Api\ItemRepositoryInterface;
use Panth\Faq\Block\Adminhtml\Item\Edit\ViewOnStorefrontButton;
use Panth\Faq\Model\ResourceModel\Item as ItemResource;
use Panth\Faq\Test\Unit\Block\Adminhtml\Category\Edit\ViewOnStorefrontButtonTest as CategoryButtonTest;

class ViewOnStorefrontButtonTest extends CategoryButtonTest
{
    protected function idParam(): string
    {
        return 'item_id';
    }

    protected function segment(): string
    {
        return 'item';
    }

    protected function repositoryClass(): string
    {
        return ItemRepositoryInterface::class;
    }

    protected function resourceClass(): string
    {
        return ItemResource::class;
    }

    protected function buttonClass(): string
    {
        return ViewOnStorefrontButton::class;
    }
}
