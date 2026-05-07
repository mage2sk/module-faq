<?php
declare(strict_types=1);
namespace Panth\Faq\Controller\Adminhtml\Category;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Panth\Faq\Api\CategoryRepositoryInterface;
use Panth\Faq\Model\CategoryFactory;
use Panth\Faq\Model\ResourceModel\Category as CategoryResource;

class Save extends Action
{
    public const ADMIN_RESOURCE = 'Panth_Faq::category_save';

    public function __construct(
        Context $context,
        protected CategoryRepositoryInterface $categoryRepository,
        protected CategoryFactory $categoryFactory,
        protected DataPersistorInterface $dataPersistor
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();

        if ($data) {
            $id = $this->getRequest()->getParam('category_id');
            // See Item/Save::execute() — same scope-resolution rule.
            $storeScopeId = (int)$this->getRequest()->getParam('store', 0);
            if ($storeScopeId === 0) {
                $storeScopeId = (int)($data['store_scope_id'] ?? 0);
            }

            try {
                $model = $id
                    ? $this->categoryRepository->getById((int)$id)
                    : $this->categoryFactory->create();

                if ($storeScopeId > 0) {
                    $useDefault = $this->normalizeUseDefault(
                        $data['use_default'] ?? [],
                        CategoryResource::SCOPED_FIELDS
                    );
                    $scopedPayload = [];
                    foreach (CategoryResource::SCOPED_FIELDS as $field) {
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

                // Same backward-compat rule as the Item save controller.
                if (isset($data['store_id'])) {
                    $model->setStores($data['store_id']);
                } elseif (!$id) {
                    $model->setStores([0]);
                }

                $this->categoryRepository->save($model);
                $this->messageManager->addSuccessMessage(__('The FAQ category has been saved.'));
                $this->dataPersistor->clear('panth_faq_category');

                if ($this->getRequest()->getParam('back')) {
                    $params = ['category_id' => $model->getId()];
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
                    __('Something went wrong while saving the FAQ category.')
                );
            }

            $this->dataPersistor->set('panth_faq_category', $data);
            $params = ['category_id' => $id];
            if ($storeScopeId > 0) {
                $params['store'] = $storeScopeId;
            }
            return $resultRedirect->setPath('*/*/edit', $params);
        }

        return $resultRedirect->setPath('*/*/');
    }

    /**
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
