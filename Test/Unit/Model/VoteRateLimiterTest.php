<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Panth\Faq\Model\VoteRateLimiter;
use PHPUnit\Framework\TestCase;

class VoteRateLimiterTest extends TestCase
{
    private array $store = [];

    private function limiter($configured): VoteRateLimiter
    {
        $this->store = [];
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturnCallback(fn ($key) => $this->store[$key] ?? false);
        $cache->method('save')->willReturnCallback(function ($data, $key) {
            $this->store[$key] = $data;
            return true;
        });
        $config = $this->createStub(ScopeConfigInterface::class);
        $config->method('getValue')->willReturn($configured);

        return new VoteRateLimiter($cache, $config);
    }

    public function testDefaultLimitIsTwentyPerIp(): void
    {
        $limiter = $this->limiter(null);
        for ($i = 0; $i < 20; $i++) {
            $this->assertFalse($limiter->isLimited('203.0.113.9'));
        }
        $this->assertTrue($limiter->isLimited('203.0.113.9'));
        $this->assertFalse($limiter->isLimited('203.0.113.10'));
    }

    public function testZeroTurnsTheLimitOff(): void
    {
        $limiter = $this->limiter('0');
        for ($i = 0; $i < 50; $i++) {
            $this->assertFalse($limiter->isLimited('203.0.113.9'));
        }
    }

    public function testConfiguredLimitIsUsed(): void
    {
        $limiter = $this->limiter('2');
        $this->assertFalse($limiter->isLimited('198.51.100.1'));
        $this->assertFalse($limiter->isLimited('198.51.100.1'));
        $this->assertTrue($limiter->isLimited('198.51.100.1'));
    }
}
