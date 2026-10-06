<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Controller\Index;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Faq\Api\ItemRepositoryInterface;
use Panth\Faq\Controller\Index\View;
use Panth\Faq\Helper\Data as FaqHelper;
use Panth\Faq\Model\ResourceModel\Item as ItemResource;
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
    private ?string $route = null;
    private ItemRepositoryInterface $repository;
    private LoggerInterface $logger;
    private array $registry = [];
    private array $updates = [];
    private bool $updateFails = false;
    private bool $storeFails = false;

    protected function setUp(): void
    {
        $this->repository = $this->createStub(ItemRepositoryInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);
    }

    private function controller(): View
    {
        $helper = $this->createStub(FaqHelper::class);
        $helper->method('isEnabled')->willReturnCallback(fn () => $this->enabled);
        $registry = $this->createStub(Registry::class);
        $registry->method('registry')->willReturnCallback(fn ($key) => $this->registry[$key] ?? null);
        $registry->method('register')->willReturnCallback(function ($key, $value) {
            $this->registry[$key] = $value;
        });
        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturn('https://shop.test/');
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturnCallback(function () use ($store) {
            if ($this->storeFails) {
                throw new \RuntimeException('no store');
            }
            return $store;
        });
        $config = $this->createStub(ScopeConfigInterface::class);
        $config->method('isSetFlag')->willReturnCallback(fn () => $this->canonical);
        $config->method('getValue')->willReturnCallback(fn () => $this->route);
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('quoteIdentifier')->willReturnCallback(fn ($v) => '`' . $v . '`');
        $connection->method('update')->willReturnCallback(function ($table, $bind, $where) {
            if ($this->updateFails) {
                throw new \RuntimeException('read only');
            }
            $this->updates[] = [$table, (string)$bind['view_count'], $where];
            return 1;
        });
        $resource = $this->createStub(ItemResource::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getMainTable')->willReturn('panth_faq_item');

        return new View(
            $this->frontendContext(),
            $this->pageFactory(),
            $this->repository,
            $helper,
            $this->logger,
            $registry,
            $storeManager,
            $config,
            $resource
        );
    }

    private function withItem(array $data): void
    {
        $item = $this->newItem($data);
        $this->repository = $this->createStub(ItemRepositoryInterface::class);
        $this->repository->method('getById')->willReturn($item);
        $this->requestParams = ['id' => (string)$data['item_id']];
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

    public function testInactiveItemIsTreatedAsMissing(): void
    {
        $this->withItem(['item_id' => 3, 'is_active' => 0]);

        $this->assertNull($this->controller()->execute());

        $this->assertSame([], $this->redirect);
        $this->assertSame([], $this->messages['error']);
        $this->assertSame([], $this->updates);
    }

    public function testRepositoryNoSuchEntity(): void
    {
        $this->repository = $this->createStub(ItemRepositoryInterface::class);
        $this->repository->method('getById')->willThrowException(new NoSuchEntityException(__('x')));
        $this->requestParams = ['id' => '3'];

        $this->assertNull($this->controller()->execute());

        $this->assertSame([], $this->redirect);
        $this->assertSame([], $this->messages['error']);
    }

    public function testUnexpectedErrorIsLogged(): void
    {
        $this->repository = $this->createStub(ItemRepositoryInterface::class);
        $this->repository->method('getById')->willThrowException(new \RuntimeException('boom'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with('Error loading FAQ item: boom');
        $this->logger = $logger;
        $this->requestParams = ['id' => '3'];

        $this->controller()->execute();

        $this->assertSame(['An error occurred while loading the FAQ item.'], $this->messages['error']);
        $this->assertSame('faq/index', $this->redirect['path']);
    }

    public function testActiveItemRendersPageWithMetadataAndCountsView(): void
    {
        $this->withItem([
            'item_id' => 3,
            'is_active' => 1,
            'question' => 'Can I return?',
            'meta_description' => 'Custom description',
            'meta_keywords' => 'returns',
        ]);

        $result = $this->controller()->execute();

        $this->assertSame($this->pageCalls['page'], $result);
        $this->assertSame('Can I return?', $this->pageCalls['title_set']);
        $this->assertSame('Custom description', $this->pageCalls['description']);
        $this->assertSame('returns', $this->pageCalls['keywords']);
        $this->assertArrayNotHasKey('remote', $this->pageCalls);
        $this->assertSame(3, $this->registry['current_faq_item']->getId());
        $this->assertSame([['panth_faq_item', '`view_count` + 1', ['item_id = ?' => 3]]], $this->updates);
    }

    public function testAlreadyRegisteredItemIsNotReplaced(): void
    {
        $existing = $this->newItem(['item_id' => 1]);
        $this->registry['current_faq_item'] = $existing;
        $this->withItem(['item_id' => 3, 'is_active' => 1, 'question' => 'Q']);

        $this->controller()->execute();

        $this->assertSame($existing, $this->registry['current_faq_item']);
    }

    public function testFallbackDescriptionStripsHtmlAndTruncates(): void
    {
        $answer = '<p>Hello&nbsp;<b>world</b></p>' . "\n\n" . str_repeat('word ', 60);
        $this->withItem(['item_id' => 3, 'is_active' => 1, 'question' => 'Q', 'answer' => $answer]);

        $this->controller()->execute();

        $description = $this->pageCalls['description'];
        $this->assertStringStartsWith('Hello world word word', $description);
        $this->assertStringEndsWith('...', $description);
        $this->assertSame(158, mb_strlen($description, 'UTF-8'));
        $this->assertStringNotContainsString('<', $description);
    }

    public function testFallbackDescriptionUsesQuestionWhenAnswerEmpty(): void
    {
        $this->withItem(['item_id' => 3, 'is_active' => 1, 'question' => '  Why?  ', 'answer' => '<p> </p>']);

        $this->controller()->execute();

        $this->assertSame('Why?', $this->pageCalls['description']);
    }

    public function testCanonicalUsesUrlKeyAndCustomRoute(): void
    {
        $this->canonical = true;
        $this->route = '/help/';
        $this->withItem(['item_id' => 3, 'is_active' => 1, 'question' => 'Q', 'url_key' => 'can-i-return']);

        $this->controller()->execute();

        $this->assertSame(
            [['https://shop.test/help/item/can-i-return', 'canonical', ['attributes' => ['rel' => 'canonical']]]],
            $this->pageCalls['remote']
        );
    }

    public function testCanonicalFallsBackToIdUrl(): void
    {
        $this->canonical = true;
        $this->withItem(['item_id' => 3, 'is_active' => 1, 'question' => 'Q']);

        $this->controller()->execute();

        $this->assertSame('https://shop.test/faq/index/view?id=3', $this->pageCalls['remote'][0][0]);
    }

    public function testCanonicalFailureIsLoggedAndSkipped(): void
    {
        $this->canonical = true;
        $this->storeFails = true;
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with('Unable to build FAQ canonical URL: no store');
        $this->logger = $logger;
        $this->withItem(['item_id' => 3, 'is_active' => 1, 'question' => 'Q']);

        $this->controller()->execute();

        $this->assertArrayNotHasKey('remote', $this->pageCalls);
    }

    public function testViewCountFailureDoesNotBreakPage(): void
    {
        $this->updateFails = true;
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with('Failed to update FAQ view count: read only');
        $this->logger = $logger;
        $this->withItem(['item_id' => 3, 'is_active' => 1, 'question' => 'Q']);

        $result = $this->controller()->execute();

        $this->assertSame($this->pageCalls['page'], $result);
    }
}
