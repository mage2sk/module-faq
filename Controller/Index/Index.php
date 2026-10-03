<?php
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
    protected $resultPageFactory;

    protected $faqHelper;

    protected $scopeConfig;

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

    public function execute()
    {
        if (!$this->faqHelper->isEnabled()) {
            $this->_forward('noroute');
            return;
        }

        $faqUrlKey = $this->scopeConfig->getValue(
            'panth_faq/general/faq_route',
            ScopeInterface::SCOPE_STORE
        );

        if (!$faqUrlKey) {
            $faqUrlKey = 'faq';
        }

        $faqUrlKey = trim($faqUrlKey, '/');
        $requestUri = trim($this->getRequest()->getRequestUri(), '/');

        $requestPath = strtok($requestUri, '?');
        $requestPath = trim($requestPath, '/');

        if (($requestPath === 'faq' || $requestPath === 'faq/index' || $requestPath === 'faq/index/index')
            && $faqUrlKey !== 'faq') {
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath($faqUrlKey);
            return $resultRedirect;
        }

        $resultPage = $this->resultPageFactory->create();

        $pageTitle = $this->faqHelper->getConfigValue(FaqHelper::XML_PATH_META_TITLE);
        if (!$pageTitle) {
            $pageTitle = __('Frequently Asked Questions');
        }
        $resultPage->getConfig()->getTitle()->set($pageTitle);

        $metaDescription = $this->faqHelper->getConfigValue(FaqHelper::XML_PATH_META_DESCRIPTION);
        if ($metaDescription) {
            $resultPage->getConfig()->setDescription($metaDescription);
        }

        $metaKeywords = $this->faqHelper->getConfigValue(FaqHelper::XML_PATH_META_KEYWORDS);
        if ($metaKeywords) {
            $resultPage->getConfig()->setKeywords($metaKeywords);
        }

        return $resultPage;
    }
}
