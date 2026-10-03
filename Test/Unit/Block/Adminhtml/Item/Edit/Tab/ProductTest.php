<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Block\Adminhtml\Item\Edit\Tab;

use Panth\Faq\Block\Adminhtml\Item\Edit\Tab\Product;

class ProductTest extends PageTest
{
    protected function blockClass(): string
    {
        return Product::class;
    }

    protected function key(): string
    {
        return 'products';
    }

    protected function selectedMethod(): string
    {
        return 'getSelectedProducts';
    }

    protected function gridRoute(): string
    {
        return 'faq/item/productsgrid';
    }
}
