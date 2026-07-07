<?php
declare(strict_types=1);

namespace Panth\Faq\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Faq\Api\ItemRepositoryInterface;
use Panth\Faq\Helper\Data as FaqHelper;
use Psr\Log\LoggerInterface;

class View extends Action implements HttpGetActionInterface
{
    const REGISTRY_CURRENT_FAQ_ITEM = 'current_faq_item';

    protected $resultPageFactory;

    protected $itemRepository;

    protected $faqHelper;

    protected $logger;

    protected $registry;

    protected $storeManager;

    protected $scopeConfig;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        ItemRepositoryInterface $itemRepository,
        FaqHelper $faqHelper,
        LoggerInterface $logger,
        Registry $registry,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->itemRepository = $itemRepository;
        $this->faqHelper = $faqHelper;
        $this->logger = $logger;
        $this->registry = $registry;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context);
    }

    public function execute()
    {
        if (!$this->faqHelper->isEnabled()) {
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('/');
            return $resultRedirect;
        }

        $itemId = (int)$this->getRequest()->getParam('id');
        if (!$itemId) {
            $this->messageManager->addErrorMessage(__('FAQ item not found.'));
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('faq/index');
            return $resultRedirect;
        }

        try {
            $item = $this->itemRepository->getById($itemId);

            if (!$item->getIsActive()) {
                throw new NoSuchEntityException(__('FAQ item is not active.'));
            }

            if (!$this->registry->registry(self::REGISTRY_CURRENT_FAQ_ITEM)) {
                $this->registry->register(self::REGISTRY_CURRENT_FAQ_ITEM, $item);
            }

            $resultPage = $this->resultPageFactory->create();
            $pageConfig = $resultPage->getConfig();

            $pageTitle = $item->getQuestion();
            $pageConfig->getTitle()->set($pageTitle);

            $metaDescription = (string)$item->getMetaDescription();
            if ($metaDescription === '') {
                $metaDescription = $this->buildFallbackDescription(
                    (string)$item->getQuestion(),
                    (string)$item->getAnswer()
                );
            }
            if ($metaDescription !== '') {
                $pageConfig->setDescription($metaDescription);
            }

            if ($item->getMetaKeywords()) {
                $pageConfig->setKeywords($item->getMetaKeywords());
            }

            if ($this->scopeConfig->isSetFlag(
                FaqHelper::XML_PATH_CANONICAL_URL,
                ScopeInterface::SCOPE_STORE
            )) {
                $canonicalUrl = $this->buildCanonicalUrl($item);
                if ($canonicalUrl !== '') {
                    $pageConfig->addRemotePageAsset(
                        $canonicalUrl,
                        'canonical',
                        ['attributes' => ['rel' => 'canonical']]
                    );
                }
            }

            $item->setViewCount((int)$item->getViewCount() + 1);
            try {
                $this->itemRepository->save($item);
            } catch (\Exception $e) {
                $this->logger->error('Failed to update FAQ view count: ' . $e->getMessage());
            }

            return $resultPage;
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('FAQ item not found.'));
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('faq/index');
            return $resultRedirect;
        } catch (\Exception $e) {
            $this->logger->error('Error loading FAQ item: ' . $e->getMessage());
            $this->messageManager->addErrorMessage(__('An error occurred while loading the FAQ item.'));
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('faq/index');
            return $resultRedirect;
        }
    }

    protected function buildCanonicalUrl($item): string
    {
        try {
            $store = $this->storeManager->getStore();
            $baseUrl = rtrim($store->getBaseUrl(), '/');

            $faqUrlKey = $this->scopeConfig->getValue(
                'panth_faq/general/faq_route',
                ScopeInterface::SCOPE_STORE
            );
            if (!$faqUrlKey) {
                $faqUrlKey = 'faq';
            }
            $faqUrlKey = trim((string)$faqUrlKey, '/');

            $urlKey = (string)$item->getUrlKey();
            if ($urlKey !== '') {
                return $baseUrl . '/' . $faqUrlKey . '/item/' . $urlKey;
            }

            return $baseUrl . '/faq/index/view?id=' . (int)$item->getId();
        } catch (\Exception $e) {
            $this->logger->error('Unable to build FAQ canonical URL: ' . $e->getMessage());
            return '';
        }
    }

    protected function buildFallbackDescription(string $question, string $answer): string
    {
        $answerText = trim(html_entity_decode(
            strip_tags($answer),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        ));

        $answerText = (string)preg_replace('/\s+/u', ' ', $answerText);

        if ($answerText === '') {
            return trim($question);
        }

        $description = $answerText;

        $maxLength = 155;
        if (function_exists('mb_strlen') && mb_strlen($description, 'UTF-8') > $maxLength) {
            $description = rtrim(mb_substr($description, 0, $maxLength, 'UTF-8')) . '...';
        } elseif (strlen($description) > $maxLength) {
            $description = rtrim(substr($description, 0, $maxLength)) . '...';
        }

        return $description;
    }
}
