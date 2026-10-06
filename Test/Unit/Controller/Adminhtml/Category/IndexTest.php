<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Controller\Adminhtml\Category;

use Panth\Faq\Controller\Adminhtml\Category\Index;
use Panth\Faq\Test\Unit\Controller\ActionContextTrait;
use Panth\Faq\Test\Unit\Controller\PageResultTrait;
use PHPUnit\Framework\TestCase;

class IndexTest extends TestCase
{
    use ActionContextTrait;
    use PageResultTrait;

    public function testRendersCategoryGrid(): void
    {
        $controller = new Index($this->actionContext(), $this->pageFactory());

        $this->assertSame($this->pageCalls['page'], $controller->execute());
        $this->assertSame('Panth_Faq::category', $this->pageCalls['menu']);
        $this->assertSame('FAQ Categories', $this->pageCalls['title']);
        $this->assertSame('Panth_Faq::category', Index::ADMIN_RESOURCE);
    }
}
