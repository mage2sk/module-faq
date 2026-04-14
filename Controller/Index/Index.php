<?php
/**
 * FAQ Index Controller
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Panth\Faq\Helper\Data as FaqHelper;

class Index extends Action implements HttpGetActionInterface
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var FaqHelper
     */
    protected $faqHelper;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param FaqHelper $faqHelper
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        FaqHelper $faqHelper,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->faqHelper = $faqHelper;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context);
    }

    /**
     * Execute view action
     *
     * @return ResultInterface
     */
    public function execute()
    {
        // Check if FAQ is enabled - show 404 if disabled
        if (!$this->faqHelper->isEnabled()) {
            $this->_forward('noroute');
            return;
        }

        // Get configured FAQ URL key
        $faqUrlKey = $this->scopeConfig->getValue(
            'panth_faq/general/url_key',
            ScopeInterface::SCOPE_STORE
        );

        if (!$faqUrlKey) {
            $faqUrlKey = 'faq';
        }

        $faqUrlKey = trim($faqUrlKey, '/');
        $requestUri = trim($this->getRequest()->getRequestUri(), '/');

        // Remove query string and fragments
        $requestPath = strtok($requestUri, '?');
        $requestPath = trim($requestPath, '/');

        // If accessing via standard /faq route but config uses different URL, redirect
        // Only redirect if path exactly matches "faq" or "faq/index" or "faq/index/index"
        if (($requestPath === 'faq' || $requestPath === 'faq/index' || $requestPath === 'faq/index/index')
            && $faqUrlKey !== 'faq') {
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath($faqUrlKey);
            return $resultRedirect;
        }

        $resultPage = $this->resultPageFactory->create();

        // Set page title from config or default
        $pageTitle = $this->faqHelper->getConfigValue(FaqHelper::XML_PATH_META_TITLE);
        if (!$pageTitle) {
            $pageTitle = __('Frequently Asked Questions');
        }
        $resultPage->getConfig()->getTitle()->set($pageTitle);

        // Set meta description
        $metaDescription = $this->faqHelper->getConfigValue(FaqHelper::XML_PATH_META_DESCRIPTION);
        if ($metaDescription) {
            $resultPage->getConfig()->setDescription($metaDescription);
        }

        // Set meta keywords
        $metaKeywords = $this->faqHelper->getConfigValue(FaqHelper::XML_PATH_META_KEYWORDS);
        if ($metaKeywords) {
            $resultPage->getConfig()->setKeywords($metaKeywords);
        }

        return $resultPage;
    }
}
