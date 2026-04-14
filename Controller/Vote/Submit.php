<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 * FAQ Vote Submit Controller — handles helpful/not-helpful voting
 */
declare(strict_types=1);

namespace Panth\Faq\Controller\Vote;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Panth\Faq\Model\ItemFactory;
use Panth\Faq\Model\ResourceModel\Item as ItemResource;
use Psr\Log\LoggerInterface;

class Submit implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private RequestInterface $request;
    private JsonFactory $resultJsonFactory;
    private ItemFactory $itemFactory;
    private ItemResource $itemResource;
    private LoggerInterface $logger;

    public function __construct(
        RequestInterface $request,
        JsonFactory $resultJsonFactory,
        ItemFactory $itemFactory,
        ItemResource $itemResource,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->itemFactory = $itemFactory;
        $this->itemResource = $itemResource;
        $this->logger = $logger;
    }

    /**
     * Skip CSRF validation for AJAX vote requests
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            // Accept both JSON body and FormData
            $itemId = 0;
            $vote = '';

            $body = $this->request->getContent();
            $jsonData = $body ? json_decode($body, true) : null;

            if (is_array($jsonData)) {
                // JSON body
                $itemId = (int)($jsonData['item_id'] ?? 0);
                $vote = $jsonData['vote'] ?? '';
            } else {
                // FormData / POST params
                $itemId = (int)$this->request->getParam('item_id', 0);
                $vote = (string)$this->request->getParam('vote', '');
            }

            if (!$itemId) {
                return $result->setData(['success' => false, 'message' => __('Item ID is required.')]);
            }

            if (!in_array($vote, ['yes', 'no'], true)) {
                return $result->setData(['success' => false, 'message' => __('Vote must be "yes" or "no".')]);
            }

            // Load FAQ item
            $item = $this->itemFactory->create();
            $this->itemResource->load($item, $itemId);

            if (!$item->getId()) {
                return $result->setData(['success' => false, 'message' => __('FAQ item not found.')]);
            }

            // Increment counter via direct SQL (avoids full model save side effects)
            $column = $vote === 'yes' ? 'helpful_count' : 'not_helpful_count';
            $connection = $this->itemResource->getConnection();
            $connection->update(
                $this->itemResource->getMainTable(),
                [$column => new \Zend_Db_Expr($column . ' + 1')],
                ['item_id = ?' => $itemId]
            );

            // Reload to get updated counts
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
}
