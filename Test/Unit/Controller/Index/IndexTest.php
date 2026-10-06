<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Controller\Index;

use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\UrlInterface;
use Panth\Faq\Controller\Index\Index;
use Panth\Faq\Helper\Data as FaqHelper;
use Panth\Faq\Test\Unit\Controller\ActionContextTrait;
use Panth\Faq\Test\Unit\Controller\PageResultTrait;
use PHPUnit\Framework\TestCase;

class IndexTest extends TestCase
{
    use ActionContextTrait;
    use PageResultTrait;

    private bool $enabled = true;
    private ?string $route = null;
    private string $uri = '/faq';
    private array $config = [];
    private array $forward = [];

    private function controller(): Index
    {
        $request = $this->createStub(Http::class);
        $request->method('getRequestUri')->willReturnCallback(fn () => $this->uri);
        $request->method('initForward')->willReturnSelf();
        $request->method('setActionName')->willReturnCallback(function ($name) use ($request) {
            $this->forward['action'] = $name;
            return $request;
        });
        $request->method('setDispatched')->willReturnCallback(function ($flag) use ($request) {
            $this->forward['dispatched'] = $flag;
            return $request;
        });
        $helper = $this->createStub(FaqHelper::class);
        $helper->method('isEnabled')->willReturnCallback(fn () => $this->enabled);
        $helper->method('getConfigValue')->willReturnCallback(fn ($path) => $this->config[$path] ?? null);
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(fn () => $this->route);

        $context = $this->actionContext(Context::class, $request);
        $context->method('getActionFlag')->willReturn($this->createStub(ActionFlag::class));
        $url = $this->createStub(UrlInterface::class);
        $url->method('getBaseUrl')->willReturn('https://shop.test/');
        $context->method('getUrl')->willReturn($url);

        return new Index($context, $this->pageFactory(), $helper, $scopeConfig);
    }

    public function testDisabledModuleForwardsToNoRoute(): void
    {
        $this->enabled = false;

        $this->assertNull($this->controller()->execute());
        $this->assertSame('noroute', $this->forward['action']);
        $this->assertFalse($this->forward['dispatched']);
    }

    public function testLegacyPathRedirectsToCustomRoute(): void
    {
        $this->route = '/help/';
        $this->uri = '/faq/index/index?q=1';

        $this->controller()->execute();

        $this->assertSame(['path' => 'help', 'params' => []], $this->redirect);
    }

    public function testCustomRouteRendersPageWithDefaultTitle(): void
    {
        $this->route = 'help';
        $this->uri = '/help';

        $result = $this->controller()->execute();

        $this->assertSame($this->pageCalls['page'], $result);
        $this->assertSame('Frequently Asked Questions', $this->pageCalls['title_set']);
        $this->assertArrayNotHasKey('description', $this->pageCalls);
        $this->assertArrayNotHasKey('keywords', $this->pageCalls);
        $this->assertSame([], $this->redirect);
    }

    public function testDefaultRouteAppliesConfiguredMetadata(): void
    {
        $this->config = [
            FaqHelper::XML_PATH_META_TITLE => 'Help',
            FaqHelper::XML_PATH_META_DESCRIPTION => 'Answers',
            FaqHelper::XML_PATH_META_KEYWORDS => 'faq,help',
        ];

        $this->controller()->execute();

        $this->assertSame('Help', $this->pageCalls['title_set']);
        $this->assertSame('Answers', $this->pageCalls['description']);
        $this->assertSame('faq,help', $this->pageCalls['keywords']);
        $this->assertSame([], $this->redirect);
    }

    public function testCanonicalPointsAtConfiguredRoute(): void
    {
        $this->route = 'help';
        $this->uri = '/help?q=returns';
        $this->config = [FaqHelper::XML_PATH_CANONICAL_URL => '1'];

        $this->controller()->execute();

        $this->assertSame(
            [['https://shop.test/help', 'canonical', ['attributes' => ['rel' => 'canonical']]]],
            $this->pageCalls['remote']
        );
    }

    public function testCanonicalIsSkippedWhenDisabled(): void
    {
        $this->controller()->execute();

        $this->assertArrayNotHasKey('remote', $this->pageCalls);
    }
}
