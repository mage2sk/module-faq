<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Controller\Adminhtml\Item;

use Panth\Faq\Controller\Adminhtml\Item\MassHideFromMain;

class MassHideFromMainTest extends MassShowOnMainTest
{
    protected function controllerClass(): string
    {
        return MassHideFromMain::class;
    }

    protected function expectedFlag(): int
    {
        return 0;
    }

    protected function expectedSuffix(): string
    {
        return 'have been hidden from main page.';
    }
}
