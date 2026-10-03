<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Block\Adminhtml\Product\Edit\Tab;

use Panth\Faq\Block\Adminhtml\Product\Edit\Tab\FaqList;
use Panth\Faq\Test\Unit\Block\Adminhtml\Category\Edit\Tab\FaqListTest as CategoryFaqListTest;

class FaqListTest extends CategoryFaqListTest
{
    protected function blockClass(): string
    {
        return FaqList::class;
    }

    protected function registryKey(): string
    {
        return 'current_product';
    }

    protected function entityGetter(): string
    {
        return 'getProduct';
    }

    protected function table(): string
    {
        return 'panth_faq_item_product';
    }

    protected function column(): string
    {
        return 'product_id';
    }
}
