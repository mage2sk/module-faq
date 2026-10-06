<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Logger;

use Magento\Framework\Filesystem\Driver\File;
use Panth\Faq\Logger\Handler;
use Panth\Faq\Logger\Logger;
use PHPUnit\Framework\TestCase;

class HandlerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/panth_faq_handler_' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        $file = $this->dir . '/var/log/faq.log';
        if (is_file($file)) {
            unlink($file);
        }
        foreach ([$this->dir . '/var/log', $this->dir . '/var', $this->dir] as $path) {
            if (is_dir($path)) {
                rmdir($path);
            }
        }
    }

    public function testWritesDebugRecordsToFaqLog(): void
    {
        $handler = new Handler(new File(), $this->dir);
        $logger = new Logger('panth_faq', [$handler]);

        $logger->debug('debug entry');
        $logger->error('error entry');
        $handler->close();

        $file = $this->dir . '/var/log/faq.log';
        $this->assertFileExists($file);
        $contents = (string)file_get_contents($file);
        $this->assertStringContainsString('panth_faq.DEBUG: debug entry', $contents);
        $this->assertStringContainsString('panth_faq.ERROR: error entry', $contents);
    }
}
