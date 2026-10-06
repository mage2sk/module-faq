<?php
declare(strict_types=1);
namespace Panth\Faq\Controller\Adminhtml\Category;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Catalog\Model\ImageUploader;
use Magento\Framework\App\ObjectManager;
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
        protected DataPersistorInterface $dataPersistor,
        private ?ImageUploader $imageUploader = null
    ) {
        parent::__construct($context);
        $this->imageUploader = $imageUploader
            ?? ObjectManager::getInstance()->get('Panth\Faq\CategoryIconImageUploader');
    }

    private function normalizeIcon($icon): ?string
    {
        if (is_string($icon) && str_starts_with(trim($icon), '[')) {
            $decoded = json_decode($icon, true);
            $icon = is_array($decoded) ? $decoded : null;
        }
        if (is_string($icon)) {
            $name = basename(trim($icon));
            return $name !== '' ? $name : null;
        }
        if (!is_array($icon) || !isset($icon[0]) || !is_array($icon[0])) {
            return null;
        }
        $entry = $icon[0];
        $name = basename((string)($entry['file'] ?? $entry['name'] ?? ''));
        if ($name === '') {
            return null;
        }
        $url = (string)($entry['url'] ?? '');
        if (isset($entry['tmp_name']) || str_contains($url, '/panth/faq/category/tmp/')) {
            $name = basename((string)$this->imageUploader->moveFileFromTmp($name, true));
        }

        return $name;
    }

    private function assertIconIsNotSvg($icon): void
    {
        $values = [];
        if (is_string($icon)) {
            $values[] = $icon;
        } elseif (is_array($icon)) {
            foreach ($icon as $entry) {
                if (is_array($entry)) {
                    foreach (['name', 'file', 'url'] as $key) {
                        if (isset($entry[$key]) && is_string($entry[$key])) {
                            $values[] = $entry[$key];
                        }
                    }
                }
            }
        }
        foreach ($values as $value) {
            $path = (string)parse_url($value, PHP_URL_PATH);
            if (preg_match('/\.svgz?$/i', $path)) {
                throw new LocalizedException(__('SVG files cannot be used as a category icon. Upload a JPG, PNG or GIF image.'));
            }
        }
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();

        if ($data) {
            $id = $this->getRequest()->getParam('category_id');
            if (!$id) {
                unset($data['category_id']);
            }

            $storeScopeId = (int)$this->getRequest()->getParam('store', 0);
            if ($storeScopeId === 0) {
                $storeScopeId = (int)($data['store_scope_id'] ?? 0);
            }

            try {
                $this->assertIconIsNotSvg($data['icon'] ?? null);
                if (array_key_exists('icon', $data)) {
                    $data['icon'] = $this->normalizeIcon($data['icon']);
                } elseif ($storeScopeId === 0 && isset($data['name'])) {
                    $data['icon'] = null;
                }

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
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage(
                    $e,
                    __('Something went wrong while saving the FAQ category.')
                );
            } catch (\Throwable $e) {
                $this->messageManager->addErrorMessage(
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
