<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Observer;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Event\Observer;
use Panth\Faq\Observer\ConfigSaveAfter;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

class ConfigSaveAfterTest extends TestCase
{
    public function testInvalidatesPageAndBlockCaches(): void
    {
        $invalidated = [];
        $typeList = $this->createStub(TypeListInterface::class);
        $typeList->method('invalidate')->willReturnCallback(function ($type) use (&$invalidated) {
            $invalidated[] = $type;
        });
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        (new ConfigSaveAfter($typeList, $logger))->execute(new Observer());

        $this->assertSame(['full_page', 'block_html'], $invalidated);
    }

    public function testFailureIsLogged(): void
    {
        $typeList = $this->createStub(TypeListInterface::class);
        $typeList->method('invalidate')->willThrowException(new \RuntimeException('cache locked'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')
            ->with('Panth_Faq: Error invalidating cache after config save: cache locked');

        (new ConfigSaveAfter($typeList, $logger))->execute(new Observer());
    }
}
