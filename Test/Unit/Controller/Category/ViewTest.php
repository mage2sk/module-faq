<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Controller\Category;

use Magento\Framework\Exception\NoSuchEntityException;
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

        return new View($this->frontendContext(), $this->pageFactory(), $this->repository, $helper, $this->logger);
    }

    public function testDisabledModuleRedirectsHome(): void
    {
        $this->enabled = false;
        $this->controller()->execute();

        $this->assertSame('/', $this->redirect['path']);
    }

    public function testMissingIdRedirectsToListing(): void
    {
        $this->controller()->execute();

        $this->assertSame('faq/index', $this->redirect['path']);
        $this->assertSame(['FAQ category not found.'], $this->messages['error']);
    }

    public function testInactiveCategoryRedirects(): void
    {
        $this->repository->method('getById')->willReturn($this->newCategory(['category_id' => 2, 'is_active' => 0]));
        $this->requestParams = ['id' => '2'];

        $this->controller()->execute();

        $this->assertSame(['FAQ category not found.'], $this->messages['error']);
        $this->assertSame('faq/index', $this->redirect['path']);
    }

    public function testNoSuchEntityRedirects(): void
    {
        $this->repository->method('getById')->willThrowException(new NoSuchEntityException(__('x')));
        $this->requestParams = ['id' => '2'];

        $this->controller()->execute();

        $this->assertSame(['FAQ category not found.'], $this->messages['error']);
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
}
