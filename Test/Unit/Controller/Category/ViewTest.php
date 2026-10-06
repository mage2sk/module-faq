<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Controller\Category;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Panth\Faq\Api\CategoryRepositoryInterface;
use Panth\Faq\Controller\Category\View;
use Panth\Faq\Helper\Data as FaqHelper;
use Panth\Faq\Test\Unit\Controller\ActionContextTrait;
use Panth\Faq\Test\Unit\Controller\PageResultTrait;
use Panth\Faq\Test\Unit\EntityTrait;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

class ViewTest extends TestCase
{
    use ActionContextTrait;
    use EntityTrait;
    use PageResultTrait;

    private bool $enabled = true;
    private bool $canonical = false;
    private string $route = '';
    private CategoryRepositoryInterface $repository;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->repository = $this->createStub(CategoryRepositoryInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);
    }

    private function controller(): View
    {
        $helper = $this->createStub(FaqHelper::class);
        $helper->method('isEnabled')->willReturnCallback(fn () => $this->enabled);
        $helper->method('getConfigValue')->willReturnCallback(
            fn ($path) => $path === FaqHelper::XML_PATH_CANONICAL_URL && $this->canonical ? '1' : null
        );
        $helper->method('getFaqRoute')->willReturnCallback(fn () => $this->route);
        $url = $this->createStub(UrlInterface::class);
        $url->method('getBaseUrl')->willReturn('https://shop.test/');
        $context = $this->frontendContext();
        $context->method('getUrl')->willReturn($url);

        return new View($context, $this->pageFactory(), $this->repository, $helper, $this->logger);
    }

    public function testDisabledModuleRedirectsHome(): void
    {
        $this->enabled = false;
        $this->controller()->execute();

        $this->assertSame('/', $this->redirect['path']);
    }

    public function testMissingIdForwardsToNotFound(): void
    {
        $this->assertNull($this->controller()->execute());

        $this->assertSame([], $this->redirect);
        $this->assertSame([], $this->messages['error']);
    }

    public function testInactiveCategoryRedirects(): void
    {
        $this->repository->method('getById')->willReturn($this->newCategory(['category_id' => 2, 'is_active' => 0]));
        $this->requestParams = ['id' => '2'];

        $this->assertNull($this->controller()->execute());

        $this->assertSame([], $this->messages['error']);
        $this->assertSame([], $this->redirect);
    }

    public function testNoSuchEntityRedirects(): void
    {
        $this->repository->method('getById')->willThrowException(new NoSuchEntityException(__('x')));
        $this->requestParams = ['id' => '2'];

        $this->assertNull($this->controller()->execute());

        $this->assertSame([], $this->messages['error']);
        $this->assertSame([], $this->redirect);
    }

    public function testUnexpectedErrorIsLogged(): void
    {
        $this->repository->method('getById')->willThrowException(new \LogicException('bad'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with('Error loading FAQ category: bad');
        $this->logger = $logger;
        $this->requestParams = ['id' => '2'];

        $this->controller()->execute();

        $this->assertSame(['An error occurred while loading the FAQ category.'], $this->messages['error']);
    }

    public function testActiveCategoryRendersWithMetadata(): void
    {
        $this->repository->method('getById')->willReturn($this->newCategory([
            'category_id' => 2,
            'is_active' => 1,
            'name' => 'Shipping',
            'meta_description' => 'Shipping answers',
            'meta_keywords' => 'shipping,delivery',
        ]));
        $this->requestParams = ['id' => '2'];

        $result = $this->controller()->execute();

        $this->assertSame($this->pageCalls['page'], $result);
        $this->assertSame('Shipping', $this->pageCalls['title_set']);
        $this->assertSame('Shipping answers', $this->pageCalls['description']);
        $this->assertSame('shipping,delivery', $this->pageCalls['keywords']);
    }

    public function testMetadataIsOptional(): void
    {
        $this->repository->method('getById')->willReturn(
            $this->newCategory(['category_id' => 2, 'is_active' => 1, 'name' => 'Billing'])
        );
        $this->requestParams = ['id' => '2'];

        $this->controller()->execute();

        $this->assertSame('Billing', $this->pageCalls['title_set']);
        $this->assertArrayNotHasKey('description', $this->pageCalls);
        $this->assertArrayNotHasKey('keywords', $this->pageCalls);
    }

    public function testCanonicalUsesConfiguredRouteAndUrlKey(): void
    {
        $this->canonical = true;
        $this->route = '/help/';
        $this->repository->method('getById')->willReturn(
            $this->newCategory(['category_id' => 2, 'is_active' => 1, 'name' => 'Billing', 'url_key' => 'billing'])
        );
        $this->requestParams = ['id' => '2'];

        $this->controller()->execute();

        $this->assertSame(
            [['https://shop.test/help/category/billing', 'canonical', ['attributes' => ['rel' => 'canonical']]]],
            $this->pageCalls['remote']
        );
    }

    public function testCanonicalDefaultsRouteAndIsSkippedWhenDisabled(): void
    {
        $this->repository->method('getById')->willReturn(
            $this->newCategory(['category_id' => 2, 'is_active' => 1, 'name' => 'Billing', 'url_key' => 'billing'])
        );
        $this->requestParams = ['id' => '2'];

        $this->controller()->execute();
        $this->assertArrayNotHasKey('remote', $this->pageCalls);

        $this->canonical = true;
        $this->pageCalls = [];
        $this->controller()->execute();
        $this->assertSame('https://shop.test/faq/category/billing', $this->pageCalls['remote'][0][0]);
    }
}
