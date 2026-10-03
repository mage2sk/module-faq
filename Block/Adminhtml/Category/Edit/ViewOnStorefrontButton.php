<?php
declare(strict_types=1);

namespace Panth\Faq\Block\Adminhtml\Category\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Faq\Api\CategoryRepositoryInterface;
use Panth\Faq\Model\ResourceModel\Category as CategoryResource;

class ViewOnStorefrontButton extends GenericButton implements ButtonProviderInterface
{
    public function __construct(
        Context $context,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly CategoryResource $categoryResource,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context);
    }

    public function getButtonData()
    {
        $url = $this->resolveStorefrontUrl();
        if (!$url) {
            return [];
        }
        return [
            'label' => __('View on Storefront'),
            'on_click' => sprintf("window.open('%s', '_blank');", $url),
            'class' => 'view',
            'sort_order' => 20,
        ];
    }

    private function resolveStorefrontUrl(): ?string
    {
        $categoryId = (int)$this->context->getRequest()->getParam('category_id');
        if ($categoryId <= 0) {
            return null;
        }
        try {
            $this->categoryRepository->getById($categoryId);
        } catch (\Throwable) {
            return null;
        }

        $scopeStoreId = (int)$this->context->getRequest()->getParam('store', 0);
        $effectiveStoreId = $scopeStoreId > 0
            ? $scopeStoreId
            : (int)$this->storeManager->getDefaultStoreView()?->getId();

        if ($effectiveStoreId <= 0) {
            return null;
        }

        $slug = null;
        if ($scopeStoreId > 0) {
            $override = $this->categoryResource->getStoreOverrideRow($categoryId, $scopeStoreId);
            if (!empty($override['url_key'])) {
                $slug = (string)$override['url_key'];
            }
        }
        if ($slug === null) {
            $defaults = $this->categoryResource->loadDefaultValuesPublic($categoryId);
            $slug = (string)($defaults['url_key'] ?? '');
        }
        if ($slug === '') {
            return null;
        }

        $store = $this->storeManager->getStore($effectiveStoreId);
        $baseUrl = (string)$store->getBaseUrl();
        $faqRoute = (string)$this->scopeConfig->getValue(
            'panth_faq/general/faq_route',
            ScopeInterface::SCOPE_STORE,
            $effectiveStoreId
        );
        if ($faqRoute === '') {
            $faqRoute = 'faq';
        }

        return rtrim($baseUrl, '/') . '/' . trim($faqRoute, '/') . '/category/' . $slug;
    }
}
