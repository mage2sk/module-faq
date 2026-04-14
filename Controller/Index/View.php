<?php
/**
 * FAQ View Controller
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
    /**
     * Registry key for the currently viewed FAQ item.
     */
    const REGISTRY_CURRENT_FAQ_ITEM = 'current_faq_item';

    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var ItemRepositoryInterface
     */
    protected $itemRepository;

    /**
     * @var FaqHelper
     */
    protected $faqHelper;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param ItemRepositoryInterface $itemRepository
     * @param FaqHelper $faqHelper
     * @param LoggerInterface $logger
     * @param Registry $registry
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     */
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

    /**
     * Execute view action
     *
     * @return ResultInterface
     */
    public function execute()
    {
        // Check if FAQ is enabled
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

            // Check if item is active
            if (!$item->getIsActive()) {
                throw new NoSuchEntityException(__('FAQ item is not active.'));
            }

            // Register the current FAQ item so blocks (schema, etc.) can scope to it
            if (!$this->registry->registry(self::REGISTRY_CURRENT_FAQ_ITEM)) {
                $this->registry->register(self::REGISTRY_CURRENT_FAQ_ITEM, $item);
            }

            $resultPage = $this->resultPageFactory->create();
            $pageConfig = $resultPage->getConfig();

            // Set page title
            $pageTitle = $item->getQuestion();
            $pageConfig->getTitle()->set($pageTitle);

            // Set meta description (fall back to a summary of the answer so the
            // tag is always present on individual FAQ pages)
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

            // Set meta keywords
            if ($item->getMetaKeywords()) {
                $pageConfig->setKeywords($item->getMetaKeywords());
            }

            // Emit a per-item canonical so individual FAQ pages no longer share
            // the same canonical URL as the listing.
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

            // Update view count
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

    /**
     * Build a canonical URL for the current FAQ item.
     *
     * Prefers the clean URL rewrite (/faq/item/<url-key>) so the canonical
     * matches the indexable URL. Falls back to the query-string route with
     * ?id=N so individual pages still get distinct canonicals.
     *
     * @param \Panth\Faq\Api\Data\ItemInterface $item
     * @return string
     */
    protected function buildCanonicalUrl($item): string
    {
        try {
            $store = $this->storeManager->getStore();
            $baseUrl = rtrim($store->getBaseUrl(), '/');

            $faqUrlKey = $this->scopeConfig->getValue(
                'panth_faq/general/url_key',
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

    /**
     * Produce a short meta description from the FAQ question/answer when the
     * admin has not supplied an explicit one.
     *
     * @param string $question
     * @param string $answer
     * @return string
     */
    protected function buildFallbackDescription(string $question, string $answer): string
    {
        $answerText = trim(html_entity_decode(
            strip_tags($answer),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        ));
        // Collapse whitespace for a clean single-line description
        $answerText = (string)preg_replace('/\s+/u', ' ', $answerText);

        if ($answerText === '') {
            return trim($question);
        }

        $description = $answerText;
        // Keep under the common ~160 char meta description budget
        $maxLength = 155;
        if (function_exists('mb_strlen') && mb_strlen($description, 'UTF-8') > $maxLength) {
            $description = rtrim(mb_substr($description, 0, $maxLength, 'UTF-8')) . '...';
        } elseif (strlen($description) > $maxLength) {
            $description = rtrim(substr($description, 0, $maxLength)) . '...';
        }

        return $description;
    }
}
