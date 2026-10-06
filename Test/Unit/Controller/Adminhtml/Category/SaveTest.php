<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Controller\Adminhtml\Category;

use Magento\Catalog\Model\ImageUploader;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Panth\Faq\Api\CategoryRepositoryInterface;
use Panth\Faq\Controller\Adminhtml\Category\Save;
use Panth\Faq\Model\Category;
use Panth\Faq\Model\CategoryFactory;
use Panth\Faq\Test\Unit\Controller\ActionContextTrait;
use Panth\Faq\Test\Unit\EntityTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SaveTest extends TestCase
{
    use ActionContextTrait;
    use EntityTrait;

    private CategoryRepositoryInterface $repository;
    private CategoryFactory $factory;
    private DataPersistorInterface $persistor;
    private ImageUploader $uploader;
    private ?Category $saved = null;
    private array $persisted = [];

    protected function setUp(): void
    {
        $this->repository = $this->createStub(CategoryRepositoryInterface::class);
        $this->repository->method('save')->willReturnCallback(function ($category) {
            $this->saved = $category;
            if (!$category->getId()) {
                $category->setId(21);
            }
            return $category;
        });
        $this->factory = $this->createStub(CategoryFactory::class);
        $this->factory->method('create')->willReturnCallback(fn () => $this->newCategory());
        $this->persistor = $this->createStub(DataPersistorInterface::class);
        $this->persistor->method('set')->willReturnCallback(function ($key, $value) {
            $this->persisted['set'][$key] = $value;
        });
        $this->persistor->method('clear')->willReturnCallback(function ($key) {
            $this->persisted['clear'][] = $key;
        });
        $this->uploader = $this->createStub(ImageUploader::class);
    }

    private function controller(): Save
    {
        return new Save($this->actionContext(), $this->repository, $this->factory, $this->persistor, $this->uploader);
    }

    public function testAclResource(): void
    {
        $this->assertSame('Panth_Faq::category_save', Save::ADMIN_RESOURCE);
        $this->acl = ['Panth_Faq::category_save'];
        $this->assertTrue($this->isAllowed($this->controller()));
    }

    public function testRedirectsToGridWithoutPostData(): void
    {
        $this->postValue = null;
        $this->controller()->execute();

        $this->assertSame(['path' => '*/*/', 'params' => []], $this->redirect);
        $this->assertNull($this->saved);
    }

    public static function svgIconProvider(): array
    {
        return [
            'plain string' => ['icon.svg'],
            'uppercase svgz' => ['ICON.SVGZ'],
            'array url with query' => [[['name' => 'x.png', 'url' => 'https://cdn.test/media/x.svg?v=2']]],
            'array file' => [[['file' => 'evil.svg']]],
        ];
    }

    #[DataProvider('svgIconProvider')]
    public function testSvgIconsAreRejected($icon): void
    {
        $this->postValue = ['name' => 'Cat', 'icon' => $icon];

        $this->controller()->execute();

        $this->assertNull($this->saved);
        $this->assertStringContainsString('SVG files cannot be used', $this->messages['error'][0]);
        $this->assertSame('panth_faq_category', array_key_first($this->persisted['set']));
        $this->assertSame('*/*/edit', $this->redirect['path']);
    }

    public static function iconProvider(): array
    {
        return [
            'string path keeps basename' => ['/media/panth/faq/category/b.jpg', 'b.jpg'],
            'blank string' => ['   ', null],
            'json encoded uploader value' => ['[{"name":"c.gif"}]', 'c.gif'],
            'invalid json array string' => ['[not json', null],
            'existing file entry prefers file' => [[['file' => 'd.png', 'name' => 'other.png', 'url' => '/m/d.png']], 'd.png'],
            'empty array' => [[], null],
            'entry without name' => [[['url' => '/m/']], null],
            'non array entry' => [['e.png'], null],
        ];
    }

    #[DataProvider('iconProvider')]
    public function testIconIsNormalised($icon, ?string $expected): void
    {
        $this->postValue = ['name' => 'Cat', 'icon' => $icon];

        $this->controller()->execute();

        $this->assertNotNull($this->saved);
        $this->assertSame($expected, $this->saved->getIcon());
    }

    public function testTemporaryUploadIsMovedOutOfTmp(): void
    {
        $uploader = $this->createMock(ImageUploader::class);
        $uploader->expects($this->exactly(2))->method('moveFileFromTmp')
            ->with('a.png', true)
            ->willReturn('panth/faq/category/a_1.png');
        $this->uploader = $uploader;

        $this->postValue = ['name' => 'Cat', 'icon' => [['name' => 'a.png', 'tmp_name' => '/tmp/php123']]];
        $this->controller()->execute();
        $this->assertSame('a_1.png', $this->saved->getIcon());

        $this->postValue = ['name' => 'Cat', 'icon' => [['name' => 'a.png', 'url' => 'https://s.test/media/panth/faq/category/tmp/a.png']]];
        $this->controller()->execute();
        $this->assertSame('a_1.png', $this->saved->getIcon());
    }

    public function testMissingIconClearsItOnDefaultScopeSave(): void
    {
        $this->postValue = ['name' => 'Cat'];

        $this->controller()->execute();

        $this->assertTrue($this->saved->hasData('icon'));
        $this->assertNull($this->saved->getIcon());
        $this->assertSame([0], $this->saved->getData('stores'));
        $this->assertSame(['The FAQ category has been saved.'], $this->messages['success']);
        $this->assertSame(['panth_faq_category'], $this->persisted['clear']);
        $this->assertSame('*/*/', $this->redirect['path']);
    }

    public function testStoreScopeSaveKeepsIconAndAppliesUseDefault(): void
    {
        $existing = $this->newCategory([
            'category_id' => 6,
            'name' => 'Default',
            'icon' => 'keep.png',
            'sort_order' => 4,
        ]);
        $this->repository = $this->createStub(CategoryRepositoryInterface::class);
        $this->repository->method('getById')->willReturn($existing);
        $this->repository->method('save')->willReturnCallback(fn ($c) => $this->saved = $c);
        $this->requestParams = ['category_id' => '6', 'store' => '2', 'back' => '1'];
        $this->postValue = [
            'name' => 'Store name',
            'description' => 'Store desc',
            'sort_order' => 50,
            'store_id' => [2],
            'use_default' => ['description' => 1, 'name' => false],
        ];

        $this->controller()->execute();

        $this->assertSame('Store name', $this->saved->getName());
        $this->assertNull($this->saved->getDescription());
        $this->assertSame('keep.png', $this->saved->getIcon());
        $this->assertSame(4, $this->saved->getData('sort_order'));
        $this->assertSame(['description'], $this->saved->getData('use_default'));
        $this->assertSame(2, $this->saved->getData('store_scope_id'));
        $this->assertSame([2], $this->saved->getData('stores'));
        $this->assertSame(['path' => '*/*/edit', 'params' => ['category_id' => 6, 'store' => 2]], $this->redirect);
    }

    public function testRepositoryLookupFailureReturnsToEdit(): void
    {
        $this->repository = $this->createStub(CategoryRepositoryInterface::class);
        $this->repository->method('getById')->willThrowException(new LocalizedException(__('gone')));
        $this->requestParams = ['category_id' => '99'];
        $this->postValue = ['name' => 'X'];

        $this->controller()->execute();

        $this->assertSame(['gone'], $this->messages['error']);
        $this->assertSame(['path' => '*/*/edit', 'params' => ['category_id' => '99']], $this->redirect);
    }

    public function testUnexpectedErrorUsesGenericMessage(): void
    {
        $this->repository = $this->createStub(CategoryRepositoryInterface::class);
        $this->repository->method('save')->willThrowException(new \RuntimeException('boom'));
        $this->postValue = ['name' => 'X'];

        $this->controller()->execute();

        $this->assertSame(['Something went wrong while saving the FAQ category.'], $this->messages['exception']);
    }

    public function testEngineErrorAddsGenericErrorMessage(): void
    {
        $this->repository = $this->createStub(CategoryRepositoryInterface::class);
        $this->repository->method('save')->willThrowException(new \Error('boom'));
        $this->postValue = ['name' => 'X'];

        $this->controller()->execute();

        $this->assertSame(['Something went wrong while saving the FAQ category.'], $this->messages['error']);
    }

    public function testEmptyPostedIdIsDroppedForNewCategory(): void
    {
        $captured = null;
        $this->repository = $this->createStub(CategoryRepositoryInterface::class);
        $this->repository->method('save')->willReturnCallback(function ($category) use (&$captured) {
            $captured = $category->hasData('category_id') ? $category->getData('category_id') : 'absent';
            return $category;
        });
        $this->postValue = ['category_id' => '', 'name' => 'Fresh', 'url_key' => 'fresh'];

        $this->controller()->execute();

        $this->assertSame('absent', $captured);
        $this->assertSame(['The FAQ category has been saved.'], $this->messages['success']);
    }
}
