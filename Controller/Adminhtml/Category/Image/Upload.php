<?php
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

    protected $imageUploader;

    private $uploadExtensionPolicy;

    public function __construct(
        Context $context,
        ImageUploader $imageUploader,
        UploadExtensionPolicy $uploadExtensionPolicy
    ) {
        parent::__construct($context);
        $this->imageUploader = $imageUploader;
        $this->uploadExtensionPolicy = $uploadExtensionPolicy;
    }

    public function execute()
    {
        $imageId = $this->_request->getParam('param_name', 'icon');

        try {
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
