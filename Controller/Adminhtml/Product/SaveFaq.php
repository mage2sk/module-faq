<?php
declare(strict_types=1);

namespace Panth\Faq\Controller\Adminhtml\Product;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Indexer\CacheContext;

class SaveFaq extends Action
{
    protected $resultJsonFactory;

    protected $resourceConnection;

    private CacheContext $cacheContext;

    private CacheInterface $cache;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        ResourceConnection $resourceConnection,
        ?CacheContext $cacheContext = null,
        ?CacheInterface $cache = null
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->resourceConnection = $resourceConnection;
        $this->cacheContext = $cacheContext ?? ObjectManager::getInstance()->get(CacheContext::class);
        $this->cache = $cache ?? ObjectManager::getInstance()->get(CacheInterface::class);
    }

    private function cleanEntityCache(string $tag, array $itemIds): void
    {
        $tags = [$tag];
        foreach ($itemIds as $itemId) {
            $tags[] = \Panth\Faq\Model\Item::CACHE_TAG . '_' . (int)$itemId;
        }
        $this->cacheContext->registerTags($tags);
        $this->_eventManager->dispatch('clean_cache_by_tags', ['object' => $this->cacheContext]);
        $this->cache->clean($tags);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            $productId = (int)$this->getRequest()->getParam('product_id');
            $faqIds = $this->getRequest()->getParam('faq_ids', '');

            if (!$productId) {
                return $result->setData(['success' => false, 'message' => __('Invalid product ID')]);
            }

            $faqIds = $faqIds ? explode(',', $faqIds) : [];
            $faqIds = array_filter(array_map('intval', $faqIds));

            $connection = $this->resourceConnection->getConnection();
            $tableName = $connection->getTableName('panth_faq_item_product');

            $previousIds = $connection->fetchCol(
                $connection->select()->from($tableName, 'item_id')->where('product_id = ?', $productId)
            );
            $connection->delete($tableName, ['product_id = ?' => $productId]);

            if (!empty($faqIds)) {
                $data = [];
                foreach ($faqIds as $faqId) {
                    $data[] = ['item_id' => $faqId, 'product_id' => $productId];
                }
                $connection->insertMultiple($tableName, $data);
            }

            $this->cleanEntityCache('cat_p_' . $productId, array_unique(array_merge($previousIds, $faqIds)));

            return $result->setData([
                'success' => true,
                'message' => __('FAQ assignments saved successfully.')
            ]);
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => __('Error saving FAQ assignments: %1', $e->getMessage())
            ]);
        }
    }

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Panth_Faq::item_save');
    }
}
