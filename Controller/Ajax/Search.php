<?php
declare(strict_types=1);

namespace Panth\Faq\Controller\Ajax;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Panth\Faq\Model\ResourceModel\Item\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

class Search implements HttpPostActionInterface
{
    protected $request;

    protected $resultJsonFactory;

    protected $itemCollectionFactory;

    protected $storeManager;

    public function __construct(
        RequestInterface $request,
        JsonFactory $resultJsonFactory,
        CollectionFactory $itemCollectionFactory,
        StoreManagerInterface $storeManager
    ) {
        $this->request = $request;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->itemCollectionFactory = $itemCollectionFactory;
        $this->storeManager = $storeManager;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            $query = $this->request->getParam('q', '');
            $categoryId = $this->request->getParam('category', 0);

            if (strlen($query) < 2) {
                return $result->setData([
                    'success' => false,
                    'message' => __('Search query must be at least 2 characters long.'),
                    'results' => []
                ]);
            }

            $collection = $this->itemCollectionFactory->create();
            $collection->addFieldToFilter('is_active', 1);

            $storeId = $this->storeManager->getStore()->getId();
            $collection->addStoreFilter($storeId);

            $collection->addFieldToFilter(
                ['question', 'answer'],
                [
                    ['like' => '%' . $query . '%'],
                    ['like' => '%' . $query . '%']
                ]
            );

            if ($categoryId > 0) {
                $collection->getSelect()->join(
                    ['faq_cat' => $collection->getTable('panth_faq_item_faq_category')],
                    'main_table.item_id = faq_cat.item_id',
                    []
                )->where('faq_cat.faq_category_id = ?', $categoryId);
            }

            $collection->setOrder('sort_order', 'ASC');
            $collection->setPageSize(50);

            $items = [];
            foreach ($collection as $item) {
                $items[] = [
                    'id' => $item->getId(),
                    'question' => $item->getQuestion(),
                    'answer' => $item->getAnswer(),
                    'url_key' => $item->getUrlKey(),
                    'view_count' => $item->getViewCount(),
                    'helpful_count' => $item->getHelpfulCount(),
                    'not_helpful_count' => $item->getNotHelpfulCount()
                ];
            }

            return $result->setData([
                'success' => true,
                'query' => $query,
                'count' => count($items),
                'results' => $items
            ]);
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => __('An error occurred while searching.'),
                'error' => $e->getMessage()
            ]);
        }
    }
}
