<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Controller\Adminhtml\Category;

use Magento\Backend\Model\View\Result\Forward;
use Magento\Framework\Controller\ResultFactory;
use Panth\Faq\Controller\Adminhtml\Category\NewAction;
use Panth\Faq\Test\Unit\Controller\ActionContextTrait;
use PHPUnit\Framework\TestCase;

class NewActionTest extends TestCase
{
    use ActionContextTrait;

    public function testForwardsToEdit(): void
    {
        $forward = $this->createMock(Forward::class);
        $forward->expects($this->once())->method('forward')->with('edit')->willReturnSelf();
        $resultFactory = $this->createStub(ResultFactory::class);
        $resultFactory->method('create')->willReturnMap([[ResultFactory::TYPE_FORWARD, [], $forward]]);

        $controller = new NewAction($this->actionContext(resultFactory: $resultFactory));

        $this->assertSame($forward, $controller->execute());
        $this->assertSame('Panth_Faq::category_save', NewAction::ADMIN_RESOURCE);
    }
}
