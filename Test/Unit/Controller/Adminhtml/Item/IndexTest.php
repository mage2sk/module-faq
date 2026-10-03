<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Controller\Adminhtml\Item;

use Panth\Faq\Controller\Adminhtml\Item\Index;
use Panth\Faq\Test\Unit\Controller\ActionContextTrait;
use Panth\Faq\Test\Unit\Controller\PageResultTrait;
use PHPUnit\Framework\TestCase;

class IndexTest extends TestCase
{
    use ActionContextTrait;
    use PageResultTrait;

    public function testRendersGridPage(): void
    {
        $controller = new Index($this->actionContext(), $this->pageFactory());

        $result = $controller->execute();

        $this->assertSame($this->pageCalls['page'], $result);
        $this->assertSame('Panth_Faq::item', $this->pageCalls['menu']);
        $this->assertSame('FAQ Items', $this->pageCalls['title']);
    }

    public function testAcl(): void
    {
        $this->assertSame('Panth_Faq::item', Index::ADMIN_RESOURCE);
        $controller = new Index($this->actionContext(), $this->pageFactory());
        $this->assertFalse($this->isAllowed($controller));
        $this->acl = ['Panth_Faq::item'];
        $this->assertTrue($this->isAllowed($controller));
    }
}
