<?php
/**
 * FAQ Helper Data
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    const XML_PATH_ENABLED = 'panth_faq/general/enabled';
    const XML_PATH_FAQ_ROUTE = 'panth_faq/general/faq_route';
    const XML_PATH_META_TITLE = 'panth_faq/general/meta_title';
    const XML_PATH_META_DESCRIPTION = 'panth_faq/general/meta_description';
    const XML_PATH_META_KEYWORDS = 'panth_faq/general/meta_keywords';

    const XML_PATH_ITEMS_PER_PAGE = 'panth_faq/display/items_per_page';
    const XML_PATH_SHOW_CATEGORY_DESC = 'panth_faq/display/show_category_description';
    const XML_PATH_SHOW_SEARCH = 'panth_faq/display/show_search';
    const XML_PATH_SHOW_CATEGORY_FILTER = 'panth_faq/display/show_category_filter';
    const XML_PATH_DEFAULT_OPEN_FAQS = 'panth_faq/display/default_open_faqs';
    const XML_PATH_SHOW_VIEW_COUNT = 'panth_faq/display/show_view_count';
    const XML_PATH_ENABLE_HELPFUL_VOTING = 'panth_faq/display/enable_helpful_voting';

    const XML_PATH_PRODUCT_ENABLED = 'panth_faq/product_page/enabled';
    const XML_PATH_PRODUCT_TITLE = 'panth_faq/product_page/title';
    const XML_PATH_PRODUCT_POSITION = 'panth_faq/product_page/position';
    const XML_PATH_PRODUCT_LIMIT = 'panth_faq/product_page/limit';

    const XML_PATH_CATEGORY_ENABLED = 'panth_faq/category_page/enabled';
    const XML_PATH_CATEGORY_TITLE = 'panth_faq/category_page/title';
    const XML_PATH_CATEGORY_POSITION = 'panth_faq/category_page/position';
    const XML_PATH_CATEGORY_LIMIT = 'panth_faq/category_page/limit';

    const XML_PATH_CMS_ENABLED = 'panth_faq/cms_page/enabled';
    const XML_PATH_CMS_TITLE = 'panth_faq/cms_page/title';

    const XML_PATH_ENABLE_SCHEMA = 'panth_faq/seo/enable_schema';
    const XML_PATH_CANONICAL_URL = 'panth_faq/seo/canonical_url';

    /**
     * Check if module is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get FAQ route
     *
     * @param int|null $storeId
     * @return string
     */
    public function getFaqRoute($storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_FAQ_ROUTE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get config value
     *
     * @param string $path
     * @param int|null $storeId
     * @return mixed
     */
    public function getConfigValue(string $path, $storeId = null)
    {
        return $this->scopeConfig->getValue(
            $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if product page FAQ is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isProductPageEnabled($storeId = null): bool
    {
        return $this->isEnabled($storeId) && $this->scopeConfig->isSetFlag(
            self::XML_PATH_PRODUCT_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if category page FAQ is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isCategoryPageEnabled($storeId = null): bool
    {
        return $this->isEnabled($storeId) && $this->scopeConfig->isSetFlag(
            self::XML_PATH_CATEGORY_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if CMS page FAQ is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isCmsPageEnabled($storeId = null): bool
    {
        return $this->isEnabled($storeId) && $this->scopeConfig->isSetFlag(
            self::XML_PATH_CMS_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if FAQ schema is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isSchemaEnabled($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLE_SCHEMA,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
