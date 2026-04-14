<?php
/**
 * FAQ Category View Controller
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Controller\Category;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\Faq\Api\CategoryRepositoryInterface;
use Panth\Faq\Helper\Data as FaqHelper;
use Psr\Log\LoggerInterface;

class View extends Action implements HttpGetActionInterface
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var CategoryRepositoryInterface
     */
    protected $categoryRepository;

    /**
     * @var FaqHelper
     */
    protected $faqHelper;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param CategoryRepositoryInterface $categoryRepository
     * @param FaqHelper $faqHelper
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        CategoryRepositoryInterface $categoryRepository,
        FaqHelper $faqHelper,
        LoggerInterface $logger
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->categoryRepository = $categoryRepository;
        $this->faqHelper = $faqHelper;
        $this->logger = $logger;
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

        $categoryId = (int)$this->getRequest()->getParam('id');
        if (!$categoryId) {
            $this->messageManager->addErrorMessage(__('FAQ category not found.'));
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('faq/index');
            return $resultRedirect;
        }

        try {
            $category = $this->categoryRepository->getById($categoryId);

            // Check if category is active
            if (!$category->getIsActive()) {
                throw new NoSuchEntityException(__('FAQ category is not active.'));
            }

            $resultPage = $this->resultPageFactory->create();

            // Set page title
            $pageTitle = $category->getName();
            $resultPage->getConfig()->getTitle()->set($pageTitle);

            // Set meta description
            if ($category->getMetaDescription()) {
                $resultPage->getConfig()->setDescription($category->getMetaDescription());
            }

            // Set meta keywords
            if ($category->getMetaKeywords()) {
                $resultPage->getConfig()->setKeywords($category->getMetaKeywords());
            }

            return $resultPage;

        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('FAQ category not found.'));
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('faq/index');
            return $resultRedirect;
        } catch (\Exception $e) {
            $this->logger->error('Error loading FAQ category: ' . $e->getMessage());
            $this->messageManager->addErrorMessage(__('An error occurred while loading the FAQ category.'));
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('faq/index');
            return $resultRedirect;
        }
    }
}
