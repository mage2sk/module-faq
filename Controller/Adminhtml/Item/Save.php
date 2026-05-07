<?php
/**
 * FAQ Item Save Controller
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Controller\Adminhtml\Item;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Panth\Faq\Api\ItemRepositoryInterface;
use Panth\Faq\Model\ItemFactory;
use Panth\Faq\Model\ResourceModel\Item as ItemResource;

class Save extends Action
{
    public const ADMIN_RESOURCE = 'Panth_Faq::item_save';

    public function __construct(
        Context $context,
        protected ItemRepositoryInterface $itemRepository,
        protected ItemFactory $itemFactory,
        protected DataPersistorInterface $dataPersistor
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();

        if ($data) {
            $id = $this->getRequest()->getParam('item_id');
            // Scope can come from either the URL ?store= param (set by the
            // store-switcher button) or from the form's hidden store_scope_id
            // field. The latter survives even when the form's submitUrl
            // doesn't carry the URL query string.
            $storeScopeId = (int)$this->getRequest()->getParam('store', 0);
            if ($storeScopeId === 0) {
                $storeScopeId = (int)($data['store_scope_id'] ?? 0);
            }

            try {
                $model = $id
                    ? $this->itemRepository->getById((int)$id)
                    : $this->itemFactory->create();

                if ($storeScopeId > 0) {
                    // Apply incoming overrides only — do NOT overwrite the
                    // main row with scoped values. We collect the per-store
                    // payload and the `use_default` flags, then persist via
                    // the resource model's saveStoreValues hook.
                    $useDefault = $this->normalizeUseDefault(
                        $data['use_default'] ?? [],
                        ItemResource::SCOPED_FIELDS
                    );
                    $scopedPayload = [];
                    foreach (ItemResource::SCOPED_FIELDS as $field) {
                        if (in_array($field, $useDefault, true)) {
                            // Inherit default — clear any local override.
                            $scopedPayload[$field] = null;
                            continue;
                        }
                        if (array_key_exists($field, $data)) {
                            $scopedPayload[$field] = $data[$field];
                        }
                    }
                    foreach ($scopedPayload as $k => $v) {
                        $model->setData($k, $v);
                    }
                    $model->setData('store_scope_id', $storeScopeId);
                    $model->setData('use_default', $useDefault);
                } else {
                    // Default scope: write straight to main row, but skip
                    // store_scope_id / use_default plumbing keys.
                    unset($data['use_default'], $data['store_scope_id']);
                    $model->setData(array_merge($model->getData(), $data));
                }

                // Backward compat: pre-1.1.0 the form had a "Show on Store
                // Views" multiselect; we removed it because the store-
                // switcher above the form now controls scope. If a custom
                // installation still posts store_id, honour it; otherwise
                // default a brand-new entity to "all stores" (store_id = 0
                // = admin/wildcard) so it's visible everywhere by default.
                // Existing entities are left as-is.
                if (isset($data['store_id'])) {
                    $model->setStores($data['store_id']);
                } elseif (!$id) {
                    $model->setStores([0]);
                }
                if (isset($data['products'])) {
                    $products = $data['products'];
                    if (is_string($products)) {
                        $products = json_decode($products, true);
                    }
                    $model->setProducts($products);
                }
                if (isset($data['catalog_categories'])) {
                    $categories = $data['catalog_categories'];
                    if (is_string($categories)) {
                        $categories = json_decode($categories, true);
                    }
                    $model->setCatalogCategories($categories);
                }
                if (isset($data['pages'])) {
                    $pages = $data['pages'];
                    if (is_string($pages)) {
                        $pages = json_decode($pages, true);
                    }
                    $model->setPages($pages);
                }

                $this->itemRepository->save($model);
                $this->messageManager->addSuccessMessage(__('The FAQ item has been saved.'));
                $this->dataPersistor->clear('panth_faq_item');

                if ($this->getRequest()->getParam('back')) {
                    $params = ['item_id' => $model->getId()];
                    if ($storeScopeId > 0) {
                        $params['store'] = $storeScopeId;
                    }
                    return $resultRedirect->setPath('*/*/edit', $params);
                }
                return $resultRedirect->setPath('*/*/');
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\Throwable $e) {
                $this->messageManager->addExceptionMessage(
                    $e,
                    __('Something went wrong while saving the FAQ item.')
                );
            }

            $this->dataPersistor->set('panth_faq_item', $data);
            $params = ['item_id' => $id];
            if ($storeScopeId > 0) {
                $params['store'] = $storeScopeId;
            }
            return $resultRedirect->setPath('*/*/edit', $params);
        }

        return $resultRedirect->setPath('*/*/');
    }

    /**
     * Translate the form's `use_default` payload into a flat list of field
     * names. The form sends either `use_default[<field>] = "1"` (associative)
     * or a numeric array of field names; both are normalised to a list.
     *
     * @param mixed $raw
     * @param string[] $allowed
     * @return string[]
     */
    private function normalizeUseDefault($raw, array $allowed): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $list = [];
        foreach ($raw as $k => $v) {
            $candidate = is_string($k) ? $k : (string)$v;
            if ($v !== '0' && $v !== 0 && $v !== false && in_array($candidate, $allowed, true)) {
                $list[] = $candidate;
            }
        }
        return array_values(array_unique($list));
    }
}
