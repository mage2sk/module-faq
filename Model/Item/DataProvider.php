<?php
declare(strict_types=1);

namespace Panth\Faq\Model\Item;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Panth\Faq\Api\ItemRepositoryInterface;
use Panth\Faq\Logger\Logger;
use Panth\Faq\Model\ResourceModel\Item as ItemResource;
use Panth\Faq\Model\ResourceModel\Item\CollectionFactory;

/**
 * FAQ item edit-form DataProvider — store-scope aware.
 *
 * When the admin opens the form with `?store=<id>` (the standard Magento
 * store-switcher querystring):
 *   - The model is reloaded through ItemRepository with `store_scope_id`
 *     set, so the resource model overlays per-store override values and
 *     populates the `use_default` array.
 *   - getMeta() injects `service.template = ui/form/element/helper/service`
 *     into every scoped field's metadata. That triggers Magento's stock
 *     "Use Default Value" inline checkbox (same one Catalog uses for
 *     scoped product attributes), which auto-binds the field's disabled
 *     state via Knockout's `isUseDefault` and submits
 *     `use_default[<field>] = 1` on form post.
 *
 * In default scope (no ?store= or store=0), the form behaves exactly as
 * pre-1.1.0 — no service template, no checkboxes, plain admin form.
 */
class DataProvider extends AbstractDataProvider
{
    protected $dataPersistor;
    protected $loadedData;
    protected $logger;
    protected ResourceConnection $resourceConnection;
    protected ItemRepositoryInterface $itemRepository;
    protected RequestInterface $request;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        DataPersistorInterface $dataPersistor,
        Logger $logger,
        ResourceConnection $resourceConnection,
        ItemRepositoryInterface $itemRepository,
        RequestInterface $request,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->dataPersistor = $dataPersistor;
        $this->logger = $logger;
        $this->resourceConnection = $resourceConnection;
        $this->itemRepository = $itemRepository;
        $this->request = $request;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * Map each scoped field to the fieldset name it lives in inside the
     * XML ui_component. The DataProvider's parent::getMeta() does NOT
     * include the XML's static field tree, so we have to rebuild that
     * shape here for the Magento UI form merger to overlay our added
     * `service.template` + `imports.isUseDefault` config onto the right
     * field at render time.
     *
     * Keep in sync with view/adminhtml/ui_component/faq_item_form.xml.
     */
    private const SCOPED_FIELD_FIELDSETS = [
        'is_active'        => 'general',
        'question'         => 'general',
        'answer'           => 'general',
        'show_on_main'     => 'general',
        'url_key'          => 'search_engine_optimization',
        'meta_title'       => 'search_engine_optimization',
        'meta_description' => 'search_engine_optimization',
        'meta_keywords'    => 'search_engine_optimization',
    ];

    public function getMeta()
    {
        $meta = parent::getMeta();
        $storeScopeId = $this->getStoreScopeIdFromRequest();
        if ($storeScopeId > 0) {
            foreach (self::SCOPED_FIELD_FIELDSETS as $field => $fieldset) {
                $meta[$fieldset]['children'][$field]['arguments']['data']['config']['service']['template']
                    = 'ui/form/element/helper/service';
                $meta[$fieldset]['children'][$field]['arguments']['data']['config']['imports']['isUseDefault']
                    = '${ $.provider }:data.use_default.' . $field;
            }
        }
        return $meta;
    }

    public function getData()
    {
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }

        try {
            $storeScopeId = $this->getStoreScopeIdFromRequest();

            $this->loadedData = [];
            foreach ($this->collection->getItems() as $item) {
                $itemId = (int)$item->getId();

                $faqCategoryIds = $this->getFaqCategoryIds($itemId);

                if ($storeScopeId > 0) {
                    $scoped = $this->itemRepository->getById($itemId);
                    $scoped->setData('store_scope_id', $storeScopeId);
                    $scoped->getResource()->load($scoped, $itemId);
                    $itemData = $scoped->getData();
                } else {
                    $itemData = $item->getData();
                }

                $itemData['category_id'] = $faqCategoryIds;
                $itemData['store_scope_id'] = $storeScopeId;

                $this->loadedData[$itemId] = $itemData;
            }

            $persisted = $this->dataPersistor->get('panth_faq_item');
            if (!empty($persisted)) {
                $tmp = $this->collection->getNewEmptyItem();
                $tmp->setData($persisted);
                $this->loadedData[$tmp->getId()] = $tmp->getData();
                $this->dataPersistor->clear('panth_faq_item');
            }

            return $this->loadedData;
        } catch (\Throwable $e) {
            $this->logger->error('FAQ Item DataProvider error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }
    }

    private function getStoreScopeIdFromRequest(): int
    {
        return (int)$this->request->getParam('store', 0);
    }

    /**
     * @return int[]
     */
    protected function getFaqCategoryIds(int $itemId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $junctionTable = $this->resourceConnection->getTableName('panth_faq_item_faq_category');
        $select = $connection->select()
            ->from($junctionTable, ['faq_category_id'])
            ->where('item_id = ?', $itemId);
        return array_map('intval', $connection->fetchCol($select));
    }
}
