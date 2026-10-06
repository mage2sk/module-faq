<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Block\Adminhtml\Page\Edit\Tab;

use Panth\Faq\Block\Adminhtml\Page\Edit\Tab\FaqList;
use Panth\Faq\Test\Unit\Block\Adminhtml\Category\Edit\Tab\FaqListTest as CategoryFaqListTest;

class FaqListTest extends CategoryFaqListTest
{
    protected function blockClass(): string
    {
        return FaqList::class;
    }

    protected function registryKey(): string
    {
        return 'cms_page';
    }

    protected function entityGetter(): string
    {
        return 'getPage';
    }

    protected function table(): string
    {
        return 'panth_faq_item_page';
    }

    protected function column(): string
    {
        return 'page_id';
    }
}
