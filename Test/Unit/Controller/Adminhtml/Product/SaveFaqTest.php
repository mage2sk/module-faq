<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Controller\Adminhtml\Product;

use Panth\Faq\Controller\Adminhtml\Product\SaveFaq;
use Panth\Faq\Test\Unit\Controller\Adminhtml\Category\SaveFaqTest as CategorySaveFaqTest;

class SaveFaqTest extends CategorySaveFaqTest
{
    protected function controllerClass(): string
    {
        return SaveFaq::class;
    }

    protected function idParam(): string
    {
        return 'product_id';
    }

    protected function table(): string
    {
        return 'panth_faq_item_product';
    }

    protected function tagPrefix(): string
    {
        return 'cat_p_';
    }

    protected function invalidMessage(): string
    {
        return 'Invalid product ID';
    }
}
