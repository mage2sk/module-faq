<?php
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

            $storeScopeId = (int)$this->getRequest()->getParam('store', 0);
            if ($storeScopeId === 0) {
                $storeScopeId = (int)($data['store_scope_id'] ?? 0);
            }

            try {
                $model = $id
                    ? $this->itemRepository->getById((int)$id)
                    : $this->itemFactory->create();

                if ($storeScopeId > 0) {
                    $useDefault = $this->normalizeUseDefault(
                        $data['use_default'] ?? [],
                        ItemResource::SCOPED_FIELDS
                    );
                    $scopedPayload = [];
                    foreach (ItemResource::SCOPED_FIELDS as $field) {
                        if (in_array($field, $useDefault, true)) {
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
                    unset($data['use_default'], $data['store_scope_id']);
                    $model->setData(array_merge($model->getData(), $data));
                }

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
