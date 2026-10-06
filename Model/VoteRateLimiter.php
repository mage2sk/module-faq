<?php
declare(strict_types=1);

namespace Panth\Faq\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class VoteRateLimiter
{
    private const XML_PATH_LIMIT = 'panth_faq/display/vote_rate_limit';
    private const CACHE_TAG = 'panth_faq_vote_limit';
    private const WINDOW_SECONDS = 3600;
    private const DEFAULT_LIMIT = 20;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function getLimit(): int
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_LIMIT, ScopeInterface::SCOPE_STORE);
        if ($value === null || trim((string)$value) === '') {
            return self::DEFAULT_LIMIT;
        }
        return max(0, (int)$value);
    }

    public function isLimited(string $clientIp): bool
    {
        $limit = $this->getLimit();
        if ($limit <= 0 || $clientIp === '') {
            return false;
        }

        $key = self::CACHE_TAG . '_' . hash('sha256', $clientIp);
        $count = (int)$this->cache->load($key) + 1;
        $this->cache->save((string)$count, $key, [self::CACHE_TAG], self::WINDOW_SECONDS);

        return $count > $limit;
    }
}
