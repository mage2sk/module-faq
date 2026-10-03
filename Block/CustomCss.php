<?php
declare(strict_types=1);

namespace Panth\Faq\Block;

use Magento\Framework\View\Element\Template;
use Magento\Store\Model\ScopeInterface;

class CustomCss extends Template
{
    public const XML_PATH_CUSTOM_CSS = 'panth_faq/design/custom_css';

    public function getCustomCss(): string
    {
        $css = (string)$this->_scopeConfig->getValue(
            self::XML_PATH_CUSTOM_CSS,
            ScopeInterface::SCOPE_STORE
        );

        $css = str_replace('<', '', $css);

        return trim($css);
    }
}
