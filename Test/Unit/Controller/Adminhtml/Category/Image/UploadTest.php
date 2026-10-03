<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Controller\Adminhtml\Category\Image;

use Magento\Catalog\Model\ImageUploader;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Panth\Core\Security\UploadExtensionPolicy;
use Panth\Faq\Controller\Adminhtml\Category\Image\Upload;
use Panth\Faq\Test\Unit\Controller\ActionContextTrait;
use PHPUnit\Framework\TestCase;

class UploadTest extends TestCase
{
    use ActionContextTrait;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private string $dir;
    private array $files = [];
    private array $json = [];
    private ImageUploader $uploader;
    private UploadExtensionPolicy $policy;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/panth_faq_upload_' . uniqid('', true);
        mkdir($this->dir);
        $this->uploader = $this->createStub(ImageUploader::class);
        $this->policy = $this->createStub(UploadExtensionPolicy::class);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    private function tmpFile(string $name, string $contents): string
    {
        $path = $this->dir . '/' . $name;
        file_put_contents($path, $contents);

        return $path;
    }

    private function execute(): array
    {
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturnCallback(
            fn ($key, $default = null) => $this->requestParams[$key] ?? $default
        );
        $request->method('getFiles')->willReturnCallback(fn ($name) => $this->files[$name] ?? null);
        $result = $this->createStub(Json::class);
        $result->method('setData')->willReturnCallback(function ($data) use ($result) {
            $this->json = $data;
            return $result;
        });
        $resultFactory = $this->createStub(ResultFactory::class);
        $resultFactory->method('create')->willReturnMap([[ResultFactory::TYPE_JSON, [], $result]]);

        $controller = new Upload(
            $this->actionContext(request: $request, resultFactory: $resultFactory),
            $this->uploader,
            $this->policy
        );
        $controller->execute();

        return $this->json;
    }

    public function testMissingFileIsReported(): void
    {
        $this->assertSame(['error' => 'No image was uploaded.', 'errorcode' => 0], $this->execute());
    }

    public function testExtensionPolicyRejectionIsReturned(): void
    {
        $this->policy = $this->createStub(UploadExtensionPolicy::class);
        $this->policy->method('assertSafeExtension')->willThrowException(new LocalizedException(__('Blocked extension')));
        $this->files['icon'] = ['name' => 'shell.phtml', 'tmp_name' => $this->tmpFile('a', 'x')];

        $this->assertSame('Blocked extension', $this->execute()['error']);
    }

    public function testDisallowedExtensionIsRejected(): void
    {
        $this->files['icon'] = ['name' => 'icon.bmp', 'tmp_name' => $this->tmpFile('bmp', base64_decode(self::PNG))];

        $this->assertSame(
            'Only JPG, PNG and GIF images can be used as a category icon.',
            $this->execute()['error']
        );
    }

    public function testMissingTemporaryFileIsRejected(): void
    {
        $this->files['icon'] = ['name' => 'icon.png', 'tmp_name' => $this->dir . '/does-not-exist'];

        $this->assertStringStartsWith('Only JPG, PNG and GIF', $this->execute()['error']);
    }

    public function testMimeTypeMustMatchExtension(): void
    {
        $this->files['icon'] = ['name' => 'photo.jpg', 'tmp_name' => $this->tmpFile('png', base64_decode(self::PNG))];

        $this->assertStringStartsWith('Only JPG, PNG and GIF', $this->execute()['error']);
    }

    public function testNonImageContentIsRejected(): void
    {
        $this->files['icon'] = ['name' => 'icon.png', 'tmp_name' => $this->tmpFile('txt', 'just text')];

        $this->assertStringStartsWith('Only JPG, PNG and GIF', $this->execute()['error']);
    }

    public function testValidImageIsSavedToTmpDirUsingRequestedParam(): void
    {
        $uploader = $this->createMock(ImageUploader::class);
        $uploader->expects($this->once())->method('saveFileToTmpDir')->with('category_icon')
            ->willReturn(['name' => 'icon.png', 'url' => 'https://s.test/media/tmp/icon.png']);
        $this->uploader = $uploader;
        $this->requestParams = ['param_name' => 'category_icon'];
        $this->files['category_icon'] = [
            'name' => 'ICON.PNG',
            'tmp_name' => $this->tmpFile('ok', base64_decode(self::PNG)),
        ];

        $this->assertSame(['name' => 'icon.png', 'url' => 'https://s.test/media/tmp/icon.png'], $this->execute());
    }

    public function testUploaderExceptionCodeIsReturned(): void
    {
        $this->uploader = $this->createStub(ImageUploader::class);
        $this->uploader->method('saveFileToTmpDir')->willThrowException(new \RuntimeException('disk full', 28));
        $this->files['icon'] = ['name' => 'icon.png', 'tmp_name' => $this->tmpFile('ok', base64_decode(self::PNG))];

        $this->assertSame(['error' => 'disk full', 'errorcode' => 28], $this->execute());
        $this->assertSame('Panth_Faq::category_save', Upload::ADMIN_RESOURCE);
    }
}
