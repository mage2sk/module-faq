<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Controller\Adminhtml\Item;

use Magento\Backend\Model\View\Result\Forward;
use Magento\Framework\Controller\ResultFactory;
use Panth\Faq\Controller\Adminhtml\Item\NewAction;
use Panth\Faq\Test\Unit\Controller\ActionContextTrait;
use PHPUnit\Framework\TestCase;

class NewActionTest extends TestCase
{
    use ActionContextTrait;

    public function testForwardsToEdit(): void
    {
        $forward = $this->createMock(Forward::class);
        $forward->expects($this->once())->method('forward')->with('edit')->willReturnSelf();
        $resultFactory = $this->createMock(ResultFactory::class);
        $resultFactory->expects($this->once())->method('create')
            ->with(ResultFactory::TYPE_FORWARD)->willReturn($forward);

        $controller = new NewAction($this->actionContext(resultFactory: $resultFactory));

        $this->assertSame($forward, $controller->execute());
        $this->assertSame('Panth_Faq::item_save', NewAction::ADMIN_RESOURCE);
    }
}
