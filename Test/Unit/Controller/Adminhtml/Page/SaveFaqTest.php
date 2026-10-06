<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Controller\Adminhtml\Page;

use Panth\Faq\Controller\Adminhtml\Page\SaveFaq;
use Panth\Faq\Test\Unit\Controller\Adminhtml\Category\SaveFaqTest as CategorySaveFaqTest;

class SaveFaqTest extends CategorySaveFaqTest
{
    protected function controllerClass(): string
    {
        return SaveFaq::class;
    }

    protected function idParam(): string
    {
        return 'page_id';
    }

    protected function table(): string
    {
        return 'panth_faq_item_page';
    }

    protected function tagPrefix(): string
    {
        return 'cms_p_';
    }

    protected function invalidMessage(): string
    {
        return 'Invalid page ID';
    }
}
