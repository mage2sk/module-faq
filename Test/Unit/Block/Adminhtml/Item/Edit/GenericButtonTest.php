<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Block\Adminhtml\Item\Edit;

use Panth\Faq\Test\Unit\Block\Adminhtml\Category\Edit\GenericButtonTest as CategoryGenericButtonTest;

class GenericButtonTest extends CategoryGenericButtonTest
{
    protected function idParam(): string
    {
        return 'item_id';
    }

    protected function idGetter(): string
    {
        return 'getItemId';
    }

    protected function buttonClass(string $name): string
    {
        return 'Panth\\Faq\\Block\\Adminhtml\\Item\\Edit\\' . $name;
    }

    protected function deleteMessage(): string
    {
        return 'Are you sure you want to delete this FAQ item?';
    }
}
