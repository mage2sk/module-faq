<?php
/**
 * FAQ Category Icon Upload Controller
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Controller\Adminhtml\Category\Image;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Catalog\Model\ImageUploader;
use Magento\Framework\Controller\ResultFactory;
use Panth\Core\Security\UploadExtensionPolicy;

class Upload extends Action
{
    const ADMIN_RESOURCE = 'Panth_Faq::category_save';

    /**
     * @var ImageUploader
     */
    protected $imageUploader;

    /**
     * @var UploadExtensionPolicy
     */
    private $uploadExtensionPolicy;

    /**
     * @param Context $context
     * @param ImageUploader $imageUploader
     * @param UploadExtensionPolicy $uploadExtensionPolicy
     */
    public function __construct(
        Context $context,
        ImageUploader $imageUploader,
        UploadExtensionPolicy $uploadExtensionPolicy
    ) {
        parent::__construct($context);
        $this->imageUploader = $imageUploader;
        $this->uploadExtensionPolicy = $uploadExtensionPolicy;
    }

    /**
     * Upload file controller action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $imageId = $this->_request->getParam('param_name', 'icon');

        try {
            // Hard executable deny-list — defense-in-depth on top of the
            // ImageUploader's own allowlist.
            if (isset($_FILES[$imageId]['name']) && is_string($_FILES[$imageId]['name'])) {
                $this->uploadExtensionPolicy->assertSafeExtension($_FILES[$imageId]['name']);
            }

            $result = $this->imageUploader->saveFileToTmpDir($imageId);
        } catch (\Exception $e) {
            $result = ['error' => $e->getMessage(), 'errorcode' => $e->getCode()];
        }

        return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData($result);
    }
}
