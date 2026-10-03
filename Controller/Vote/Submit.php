<?php
declare(strict_types=1);

namespace Panth\Faq\Controller\Vote;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Session\Generic as GenericSession;
use Panth\Faq\Model\ItemFactory;
use Panth\Faq\Model\ResourceModel\Item as ItemResource;
use Panth\Faq\Model\VoteRateLimiter;
use Psr\Log\LoggerInterface;

class Submit implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private const SESSION_KEY = 'panth_faq_voted_items';

    private RequestInterface $request;
    private JsonFactory $resultJsonFactory;
    private ItemFactory $itemFactory;
    private ItemResource $itemResource;
    private LoggerInterface $logger;
    private FormKey $formKey;
    private GenericSession $session;
    private VoteRateLimiter $voteRateLimiter;
    private RemoteAddress $remoteAddress;
    private ?array $jsonData = null;
    private ScopeConfigInterface $scopeConfig;
    private StoreManagerInterface $storeManager;

    public function __construct(
        RequestInterface $request,
        JsonFactory $resultJsonFactory,
        ItemFactory $itemFactory,
        ItemResource $itemResource,
        LoggerInterface $logger,
        ?FormKey $formKey = null,
        ?GenericSession $session = null,
        ?VoteRateLimiter $voteRateLimiter = null,
        ?RemoteAddress $remoteAddress = null,
        ?ScopeConfigInterface $scopeConfig = null,
        ?StoreManagerInterface $storeManager = null
    ) {
        $this->request = $request;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->itemFactory = $itemFactory;
        $this->itemResource = $itemResource;
        $this->logger = $logger;
        $this->formKey = $formKey ?: ObjectManager::getInstance()->get(FormKey::class);
        $this->session = $session ?: ObjectManager::getInstance()->get(GenericSession::class);
        $this->voteRateLimiter = $voteRateLimiter ?: ObjectManager::getInstance()->get(VoteRateLimiter::class);
        $this->remoteAddress = $remoteAddress ?: ObjectManager::getInstance()->get(RemoteAddress::class);
        $this->scopeConfig = $scopeConfig ?: ObjectManager::getInstance()->get(ScopeConfigInterface::class);
        $this->storeManager = $storeManager ?: ObjectManager::getInstance()->get(StoreManagerInterface::class);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        $result = $this->resultJsonFactory->create();
        $result->setHttpResponseCode(403);
        $result->setData([
            'success' => false,
            'message' => __('Invalid form key. Please refresh the page.'),
        ]);

        return new InvalidRequestException($result);
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        $submitted = $request->getParam('form_key');
        if (!is_string($submitted) || $submitted === '') {
            $json = $this->getJsonData();
            $submitted = $json['form_key'] ?? '';
        }
        if (!is_string($submitted) || $submitted === '') {
            return false;
        }

        return hash_equals((string)$this->formKey->getFormKey(), $submitted);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            $jsonData = $this->getJsonData();

            if ($jsonData !== null) {
                $itemId = (int)($jsonData['item_id'] ?? 0);
                $vote = $jsonData['vote'] ?? '';
            } else {
                $itemId = (int)$this->request->getParam('item_id', 0);
                $vote = $this->request->getParam('vote', '');
            }

            $storeId = (int)$this->storeManager->getStore()->getId();
            if (!$this->scopeConfig->isSetFlag('panth_faq/general/enabled', ScopeInterface::SCOPE_STORE, $storeId)
                || !$this->scopeConfig->isSetFlag(
                    'panth_faq/display/enable_helpful_voting',
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                )
            ) {
                return $result->setData(['success' => false, 'message' => __('Voting is not available.')]);
            }

            if ($itemId <= 0) {
                return $result->setData(['success' => false, 'message' => __('Item ID is required.')]);
            }

            if (!is_string($vote) || !in_array($vote, ['yes', 'no'], true)) {
                return $result->setData(['success' => false, 'message' => __('Vote must be "yes" or "no".')]);
            }

            if ($this->voteRateLimiter->isLimited((string)$this->remoteAddress->getRemoteAddress())) {
                return $result->setData([
                    'success' => false,
                    'message' => __('Too many votes from your connection. Please try again later.'),
                ]);
            }

            $voted = $this->session->getData(self::SESSION_KEY);
            if (!is_array($voted)) {
                $voted = [];
            }
            if (isset($voted[$itemId])) {
                return $result->setData([
                    'success' => false,
                    'message' => __('You have already voted on this FAQ item.'),
                ]);
            }

            $item = $this->itemFactory->create();
            $this->itemResource->load($item, $itemId);

            if (!$item->getId() || !$this->isVisibleInStore($item, $storeId)) {
                return $result->setData(['success' => false, 'message' => __('FAQ item not found.')]);
            }

            $column = $vote === 'yes' ? 'helpful_count' : 'not_helpful_count';
            $connection = $this->itemResource->getConnection();
            $connection->update(
                $this->itemResource->getMainTable(),
                [$column => new \Zend_Db_Expr($connection->quoteIdentifier($column) . ' + 1')],
                ['item_id = ?' => $itemId]
            );

            $voted[$itemId] = $vote;
            $this->session->setData(self::SESSION_KEY, $voted);

            $this->itemResource->load($item, $itemId);

            return $result->setData([
                'success' => true,
                'helpful_count' => (int)$item->getData('helpful_count'),
                'not_helpful_count' => (int)$item->getData('not_helpful_count'),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('FAQ Vote error: ' . $e->getMessage());
            return $result->setData(['success' => false, 'message' => __('An error occurred.')]);
        }
    }

    private function isVisibleInStore($item, int $storeId): bool
    {
        $active = (int)$item->getData('is_active');
        $override = $this->itemResource->getStoreOverrideRow((int)$item->getId(), $storeId);
        if (isset($override['is_active']) && $override['is_active'] !== null && $override['is_active'] !== '') {
            $active = (int)$override['is_active'];
        }
        if ($active !== 1) {
            return false;
        }
        $stores = array_map('intval', (array)$item->getData('stores'));

        return $stores === [] || in_array(0, $stores, true) || in_array($storeId, $stores, true);
    }

    private function getJsonData(): ?array
    {
        if ($this->jsonData === null) {
            $body = (string)$this->request->getContent();
            $decoded = $body !== '' ? json_decode($body, true) : null;
            $this->jsonData = is_array($decoded) ? $decoded : [];
        }

        return $this->jsonData === [] ? null : $this->jsonData;
    }
}
