<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Controller\Vote;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Session\Generic;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Faq\Controller\Vote\Submit;
use Panth\Faq\Model\ItemFactory;
use Panth\Faq\Model\ResourceModel\Item as ItemResource;
use Panth\Faq\Model\VoteRateLimiter;
use Panth\Faq\Test\Unit\EntityTrait;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

class SubmitTest extends TestCase
{
    use EntityTrait;

    private array $params = [];
    private string $body = '';
    private array $flags = [
        'panth_faq/general/enabled' => true,
        'panth_faq/display/enable_helpful_voting' => true,
    ];
    private bool $limited = false;
    private array $sessionData = [];
    private ?array $row = ['item_id' => 7, 'is_active' => 1, 'stores' => [0], 'helpful_count' => 2, 'not_helpful_count' => 1];
    private array $override = [];
    private array $updates = [];
    private array $json = [];
    private ?int $httpCode = null;
    private ?\Throwable $loadError = null;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createStub(LoggerInterface::class);
    }

    private function controller(): Submit
    {
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturnCallback(
            fn ($key, $default = null) => $this->params[$key] ?? $default
        );
        $request->method('getContent')->willReturnCallback(fn () => $this->body);

        $result = $this->createStub(Json::class);
        $result->method('setData')->willReturnCallback(function ($data) use ($result) {
            $this->json = array_map(static fn ($v) => is_object($v) ? (string)$v : $v, $data);
            return $result;
        });
        $result->method('setHttpResponseCode')->willReturnCallback(function ($code) use ($result) {
            $this->httpCode = $code;
            return $result;
        });
        $jsonFactory = $this->createStub(JsonFactory::class);
        $jsonFactory->method('create')->willReturn($result);

        $itemFactory = $this->createStub(ItemFactory::class);
        $itemFactory->method('create')->willReturnCallback(fn () => $this->newItem());

        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('quoteIdentifier')->willReturnCallback(fn ($v) => '`' . $v . '`');
        $connection->method('update')->willReturnCallback(function ($table, $bind, $where) {
            $this->updates[] = [$table, array_map('strval', $bind), $where];
            $column = array_key_first($bind);
            $this->row[$column]++;
            return 1;
        });
        $resource = $this->createStub(ItemResource::class);
        $resource->method('load')->willReturnCallback(function ($item, $id) use ($resource) {
            if ($this->loadError) {
                throw $this->loadError;
            }
            if ($this->row !== null && (int)$this->row['item_id'] === (int)$id) {
                $item->setData($this->row);
            }
            return $resource;
        });
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getMainTable')->willReturn('panth_faq_item');
        $resource->method('getStoreOverrideRow')->willReturnCallback(fn () => $this->override);

        $formKey = $this->createStub(FormKey::class);
        $formKey->method('getFormKey')->willReturn('secret-key');

        $session = $this->createStub(Generic::class);
        $session->method('getData')->willReturnCallback(fn ($key = '') => $this->sessionData[$key] ?? null);
        $session->method('__call')->willReturnCallback(function ($method, $args) use ($session) {
            if ($method === 'setData') {
                $this->sessionData[$args[0]] = $args[1];
            }
            return $session;
        });

        $limiter = $this->createStub(VoteRateLimiter::class);
        $limiter->method('isLimited')->willReturnCallback(fn () => $this->limited);
        $remote = $this->createStub(RemoteAddress::class);
        $remote->method('getRemoteAddress')->willReturn('203.0.113.5');

        $config = $this->createStub(ScopeConfigInterface::class);
        $config->method('isSetFlag')->willReturnCallback(fn ($path) => $this->flags[$path] ?? false);
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new Submit(
            $request,
            $jsonFactory,
            $itemFactory,
            $resource,
            $this->logger,
            $formKey,
            $session,
            $limiter,
            $remote,
            $config,
            $storeManager
        );
    }

    public function testCsrfAcceptsMatchingFormKeyParam(): void
    {
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturnMap([['form_key', null, 'secret-key']]);

        $this->assertTrue($this->controller()->validateForCsrf($request));
    }

    public function testCsrfValidationReadsTheGivenRequestAndJsonBody(): void
    {
        $controller = $this->controller();
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturn('wrong');
        $this->assertFalse($controller->validateForCsrf($request));

        $this->body = json_encode(['form_key' => 'secret-key']);
        $controller = $this->controller();
        $this->assertTrue($controller->validateForCsrf($this->createStub(Http::class)));
    }

    public function testCsrfRejectsMissingKey(): void
    {
        $this->body = 'not json';
        $this->assertFalse($this->controller()->validateForCsrf($this->createStub(Http::class)));
    }

    public function testCsrfExceptionCarries403Json(): void
    {
        $exception = $this->controller()->createCsrfValidationException($this->createStub(Http::class));

        $this->assertInstanceOf(InvalidRequestException::class, $exception);
        $this->assertSame(403, $this->httpCode);
        $this->assertSame(
            ['success' => false, 'message' => 'Invalid form key. Please refresh the page.'],
            $this->json
        );
    }

    public function testVotingDisabledInConfig(): void
    {
        $this->flags['panth_faq/display/enable_helpful_voting'] = false;
        $this->params = ['item_id' => '7', 'vote' => 'yes'];

        $this->controller()->execute();

        $this->assertSame(['success' => false, 'message' => 'Voting is not available.'], $this->json);
        $this->assertSame([], $this->updates);
    }

    public function testModuleDisabled(): void
    {
        $this->flags['panth_faq/general/enabled'] = false;
        $this->params = ['item_id' => '7', 'vote' => 'yes'];

        $this->controller()->execute();

        $this->assertSame('Voting is not available.', $this->json['message']);
    }

    public function testItemIdIsRequired(): void
    {
        $this->params = ['vote' => 'yes'];

        $this->controller()->execute();

        $this->assertSame(['success' => false, 'message' => 'Item ID is required.'], $this->json);
    }

    public function testVoteValueIsValidated(): void
    {
        $this->params = ['item_id' => '7', 'vote' => 'maybe'];

        $this->controller()->execute();

        $this->assertSame('Vote must be "yes" or "no".', $this->json['message']);

        $this->params = ['item_id' => '7', 'vote' => ['yes']];
        $this->controller()->execute();
        $this->assertSame('Vote must be "yes" or "no".', $this->json['message']);
    }

    public function testRateLimitedClientsAreRejected(): void
    {
        $this->limited = true;
        $this->params = ['item_id' => '7', 'vote' => 'no'];

        $this->controller()->execute();

        $this->assertSame('Too many votes from your connection. Please try again later.', $this->json['message']);
        $this->assertSame([], $this->updates);
    }

    public function testSecondVoteInSameSessionIsRejected(): void
    {
        $this->sessionData['panth_faq_voted_items'] = [7 => 'yes'];
        $this->params = ['item_id' => '7', 'vote' => 'no'];

        $this->controller()->execute();

        $this->assertSame('You have already voted on this FAQ item.', $this->json['message']);
    }

    public function testUnknownItemIsNotFound(): void
    {
        $this->params = ['item_id' => '99', 'vote' => 'yes'];

        $this->controller()->execute();

        $this->assertSame(['success' => false, 'message' => 'FAQ item not found.'], $this->json);
    }

    public function testStoreOverrideCanDeactivateItem(): void
    {
        $this->override = ['is_active' => '0'];
        $this->params = ['item_id' => '7', 'vote' => 'yes'];

        $this->controller()->execute();

        $this->assertSame('FAQ item not found.', $this->json['message']);
    }

    public function testStoreOverrideCanActivateItem(): void
    {
        $this->row['is_active'] = 0;
        $this->override = ['is_active' => '1'];
        $this->params = ['item_id' => '7', 'vote' => 'yes'];

        $this->controller()->execute();

        $this->assertTrue($this->json['success']);
    }

    public function testItemAssignedToOtherStoreIsNotFound(): void
    {
        $this->row['stores'] = ['3'];
        $this->params = ['item_id' => '7', 'vote' => 'yes'];

        $this->controller()->execute();

        $this->assertSame('FAQ item not found.', $this->json['message']);
    }

    public function testHelpfulVoteIncrementsCounterAndRemembersVote(): void
    {
        $this->row['stores'] = ['1', '2'];
        $this->params = ['item_id' => '7', 'vote' => 'yes'];

        $this->controller()->execute();

        $this->assertSame(
            [['panth_faq_item', ['helpful_count' => '`helpful_count` + 1'], ['item_id = ?' => 7]]],
            $this->updates
        );
        $this->assertSame([7 => 'yes'], $this->sessionData['panth_faq_voted_items']);
        $this->assertSame(['success' => true, 'helpful_count' => 3, 'not_helpful_count' => 1], $this->json);
    }

    public function testJsonBodyIsPreferredOverParams(): void
    {
        $this->body = json_encode(['item_id' => 7, 'vote' => 'no']);
        $this->params = ['item_id' => '1', 'vote' => 'yes'];

        $this->controller()->execute();

        $this->assertSame('`not_helpful_count` + 1', $this->updates[0][1]['not_helpful_count']);
        $this->assertSame(['success' => true, 'helpful_count' => 2, 'not_helpful_count' => 2], $this->json);
    }

    public function testExceptionsAreLoggedAndHidden(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with('FAQ Vote error: deadlock');
        $this->logger = $logger;
        $this->loadError = new \RuntimeException('deadlock');
        $this->params = ['item_id' => '7', 'vote' => 'yes'];

        $this->controller()->execute();

        $this->assertSame(['success' => false, 'message' => 'An error occurred.'], $this->json);
    }
}
