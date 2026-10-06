<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Controller\Adminhtml\Item;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Panth\Faq\Api\ItemRepositoryInterface;
use Panth\Faq\Controller\Adminhtml\Item\Save;
use Panth\Faq\Model\Item;
use Panth\Faq\Model\ItemFactory;
use Panth\Faq\Test\Unit\Controller\ActionContextTrait;
use Panth\Faq\Test\Unit\EntityTrait;
use PHPUnit\Framework\TestCase;

class SaveTest extends TestCase
{
    use ActionContextTrait;
    use EntityTrait;

    private ItemRepositoryInterface $repository;
    private ItemFactory $factory;
    private DataPersistorInterface $persistor;
    private ?Item $saved = null;
    private array $persisted = [];

    protected function setUp(): void
    {
        $this->repository = $this->createStub(ItemRepositoryInterface::class);
        $this->repository->method('save')->willReturnCallback(function ($item) {
            $this->saved = $item;
            if (!$item->getId()) {
                $item->setId(55);
            }
            return $item;
        });
        $this->factory = $this->createStub(ItemFactory::class);
        $this->factory->method('create')->willReturnCallback(fn () => $this->newItem());
        $this->persistor = $this->createStub(DataPersistorInterface::class);
        $this->persistor->method('set')->willReturnCallback(function ($key, $value) {
            $this->persisted['set'][$key] = $value;
        });
        $this->persistor->method('clear')->willReturnCallback(function ($key) {
            $this->persisted['clear'][] = $key;
        });
    }

    private function controller(): Save
    {
        return new Save($this->actionContext(), $this->repository, $this->factory, $this->persistor);
    }

    public function testAclResource(): void
    {
        $this->assertSame('Panth_Faq::item_save', Save::ADMIN_RESOURCE);
        $controller = $this->controller();
        $this->assertFalse($this->isAllowed($controller));
        $this->acl = ['Panth_Faq::item_save'];
        $this->assertTrue($this->isAllowed($controller));
    }

    public function testRedirectsToGridWhenNoPostData(): void
    {
        $this->postValue = [];
        $this->controller()->execute();

        $this->assertSame('*/*/', $this->redirect['path']);
        $this->assertNull($this->saved);
    }

    public function testCreatesNewItemWithRelationsDecodedFromJson(): void
    {
        $this->postValue = [
            'question' => 'Q?',
            'store_id' => [1, 2],
            'products' => '[5,6]',
            'catalog_categories' => [3],
            'pages' => '[]',
            'use_default' => ['question' => '1'],
            'store_scope_id' => '0',
        ];

        $this->controller()->execute();

        $this->assertNotNull($this->saved);
        $this->assertSame('Q?', $this->saved->getQuestion());
        $this->assertSame([1, 2], $this->saved->getData('stores'));
        $this->assertSame([5, 6], $this->saved->getData('products'));
        $this->assertSame([3], $this->saved->getData('catalog_categories'));
        $this->assertSame([], $this->saved->getData('pages'));
        $this->assertFalse($this->saved->hasData('use_default'));
        $this->assertFalse($this->saved->hasData('store_scope_id'));
        $this->assertSame(['The FAQ item has been saved.'], $this->messages['success']);
        $this->assertSame(['panth_faq_item'], $this->persisted['clear']);
        $this->assertSame('*/*/', $this->redirect['path']);
    }

    public function testNewItemWithoutStoresDefaultsToAllStores(): void
    {
        $this->postValue = ['question' => 'Q?'];

        $this->controller()->execute();

        $this->assertSame([0], $this->saved->getData('stores'));
        $this->assertFalse($this->saved->hasData('products'));
    }

    public function testExistingItemKeepsStoresWhenNotPosted(): void
    {
        $existing = $this->newItem(['item_id' => 8, 'question' => 'Old', 'sort_order' => 3]);
        $this->repository = $this->createStub(ItemRepositoryInterface::class);
        $this->repository->method('getById')->willReturn($existing);
        $this->repository->method('save')->willReturnCallback(fn ($item) => $this->saved = $item);
        $this->requestParams = ['item_id' => '8', 'back' => '1'];
        $this->postValue = ['question' => 'New'];

        $this->controller()->execute();

        $this->assertSame('New', $this->saved->getQuestion());
        $this->assertSame(3, $this->saved->getData('sort_order'));
        $this->assertFalse($this->saved->hasData('stores'));
        $this->assertSame(['path' => '*/*/edit', 'params' => ['item_id' => 8]], $this->redirect);
    }

    public function testStoreScopeSaveOnlyCopiesScopedFieldsAndUseDefaultFlags(): void
    {
        $existing = $this->newItem([
            'item_id' => 8,
            'question' => 'Default Q',
            'answer' => 'Default A',
            'sort_order' => 1,
        ]);
        $this->repository = $this->createStub(ItemRepositoryInterface::class);
        $this->repository->method('getById')->willReturn($existing);
        $this->repository->method('save')->willReturnCallback(fn ($item) => $this->saved = $item);
        $this->requestParams = ['item_id' => '8', 'store' => '2', 'back' => '1'];
        $this->postValue = [
            'question' => 'Store Q',
            'answer' => 'Store A',
            'url_key' => 'store-key',
            'sort_order' => 99,
            'use_default' => ['question' => '1', 'answer' => '0', 'bogus' => '1', 0 => 'meta_title'],
        ];

        $this->controller()->execute();

        $this->assertNull($this->saved->getQuestion());
        $this->assertSame('Store A', $this->saved->getAnswer());
        $this->assertSame('store-key', $this->saved->getUrlKey());
        $this->assertSame(1, $this->saved->getData('sort_order'));
        $this->assertNull($this->saved->getMetaTitle());
        $this->assertTrue($this->saved->hasData('meta_title'));
        $this->assertSame(2, $this->saved->getData('store_scope_id'));
        $this->assertSame(['question', 'meta_title'], $this->saved->getData('use_default'));
        $this->assertSame(['path' => '*/*/edit', 'params' => ['item_id' => 8, 'store' => 2]], $this->redirect);
    }

    public function testStoreScopeCanComeFromPostedField(): void
    {
        $existing = $this->newItem(['item_id' => 8]);
        $this->repository = $this->createStub(ItemRepositoryInterface::class);
        $this->repository->method('getById')->willReturn($existing);
        $this->repository->method('save')->willReturnCallback(fn ($item) => $this->saved = $item);
        $this->requestParams = ['item_id' => '8'];
        $this->postValue = ['store_scope_id' => '3', 'question' => 'Scoped', 'use_default' => 'not-an-array'];

        $this->controller()->execute();

        $this->assertSame(3, $this->saved->getData('store_scope_id'));
        $this->assertSame([], $this->saved->getData('use_default'));
        $this->assertSame('Scoped', $this->saved->getQuestion());
    }

    public function testLocalizedExceptionPersistsDataAndReturnsToEdit(): void
    {
        $this->repository = $this->createStub(ItemRepositoryInterface::class);
        $this->repository->method('getById')->willReturn($this->newItem(['item_id' => 4]));
        $this->repository->method('save')->willThrowException(new LocalizedException(__('URL key taken')));
        $this->requestParams = ['item_id' => '4', 'store' => '1'];
        $this->postValue = ['question' => 'Q'];

        $this->controller()->execute();

        $this->assertSame(['URL key taken'], $this->messages['error']);
        $this->assertSame(['question' => 'Q'], $this->persisted['set']['panth_faq_item']);
        $this->assertSame(['path' => '*/*/edit', 'params' => ['item_id' => '4', 'store' => 1]], $this->redirect);
    }

    public function testUnexpectedErrorAddsExceptionMessage(): void
    {
        $this->repository = $this->createStub(ItemRepositoryInterface::class);
        $this->repository->method('save')->willThrowException(new \RuntimeException('db down'));
        $this->postValue = ['question' => 'Q'];

        $this->controller()->execute();

        $this->assertSame(['Something went wrong while saving the FAQ item.'], $this->messages['exception']);
        $this->assertSame(['path' => '*/*/edit', 'params' => ['item_id' => null]], $this->redirect);
        $this->assertSame([], $this->messages['success']);
    }

    public function testEngineErrorAddsGenericErrorMessage(): void
    {
        $this->repository = $this->createStub(ItemRepositoryInterface::class);
        $this->repository->method('save')->willThrowException(new \TypeError('bad type'));
        $this->postValue = ['question' => 'Q'];

        $this->controller()->execute();

        $this->assertSame(['Something went wrong while saving the FAQ item.'], $this->messages['error']);
        $this->assertSame(['path' => '*/*/edit', 'params' => ['item_id' => null]], $this->redirect);
    }

    public function testEmptyPostedIdIsDroppedForNewItem(): void
    {
        $this->postValue = ['item_id' => '', 'question' => 'Fresh?'];

        $this->controller()->execute();

        $this->assertSame(55, $this->saved->getId());
        $this->assertSame('Fresh?', $this->saved->getQuestion());
        $this->assertSame(['The FAQ item has been saved.'], $this->messages['success']);
    }

    public function testEmptyPostedIdIsNotTreatedAsUpdate(): void
    {
        $captured = null;
        $this->repository = $this->createStub(ItemRepositoryInterface::class);
        $this->repository->method('save')->willReturnCallback(function ($item) use (&$captured) {
            $captured = $item->hasData('item_id') ? $item->getData('item_id') : 'absent';
            return $item;
        });
        $this->postValue = ['item_id' => '', 'question' => 'Fresh?'];

        $this->controller()->execute();

        $this->assertSame('absent', $captured);
    }
}
