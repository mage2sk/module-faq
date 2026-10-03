<?php
declare(strict_types=1);

namespace Panth\Faq\Controller\Adminhtml\Category\Image;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Catalog\Model\ImageUploader;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Panth\Core\Security\UploadExtensionPolicy;

class Upload extends Action implements HttpPostActionInterface
{
    const ADMIN_RESOURCE = 'Panth_Faq::category_save';

    private const ALLOWED_TYPES = [
        'jpg' => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'gif' => ['image/gif'],
        'png' => ['image/png'],
    ];

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
        $imageId = (string)$this->_request->getParam('param_name', 'icon');

        try {
            $file = $this->_request->getFiles($imageId);
            if (!is_array($file) || !isset($file['name'], $file['tmp_name'])
                || !is_string($file['name']) || !is_string($file['tmp_name'])
            ) {
                throw new LocalizedException(__('No image was uploaded.'));
            }

            $this->uploadExtensionPolicy->assertSafeExtension($file['name']);
            $this->assertAllowedImage($file['name'], $file['tmp_name']);

            $result = $this->imageUploader->saveFileToTmpDir($imageId);
        } catch (\Exception $e) {
            $result = ['error' => $e->getMessage(), 'errorcode' => $e->getCode()];
        }

        return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData($result);
    }

    private function assertAllowedImage(string $name, string $tmpName): void
    {
        $message = __('Only JPG, PNG and GIF images can be used as a category icon.');
        $extension = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED_TYPES[$extension]) || $tmpName === '' || !is_file($tmpName)) {
            throw new LocalizedException($message);
        }

        $mimeType = strtolower((string)(new \finfo(FILEINFO_MIME_TYPE))->file($tmpName));
        if (!in_array($mimeType, self::ALLOWED_TYPES[$extension], true)) {
            throw new LocalizedException($message);
        }

        $imageInfo = getimagesize($tmpName);
        if ($imageInfo === false || !in_array(strtolower((string)$imageInfo['mime']), self::ALLOWED_TYPES[$extension], true)) {
            throw new LocalizedException($message);
        }
    }
}
