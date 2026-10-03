<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Block;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Select;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Faq\Helper\Data as FaqHelper;
use Panth\Faq\Model\ResourceModel\Item\Collection;
use Panth\Faq\Model\ResourceModel\Item\CollectionFactory;

trait BlockTestTrait
{
    protected array $params = [];
    protected string $fullActionName = '';
    protected array $configValues = [];
    protected array $helperFlags = [];
    protected array $collectionCalls = [];
    protected array $collections = [];
    protected array $collectionItems = [];

    protected function templateContext(): Context
    {
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturnCallback(
            fn ($key, $default = null) => $this->params[$key] ?? $default
        );
        $request->method('getFullActionName')->willReturnCallback(fn () => $this->fullActionName);
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            fn ($route = '', $params = []) => 'https://shop.test/' . $route . ($params ? '?' . http_build_query($params) : '')
        );
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(fn ($path) => $this->configValues[$path] ?? null);

        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getUrlBuilder')->willReturn($url);
        $context->method('getScopeConfig')->willReturn($scopeConfig);

        return $context;
    }

    protected function scopeConfig(): ScopeConfigInterface
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(fn ($path) => $this->configValues[$path] ?? null);

        return $scopeConfig;
    }

    protected function faqHelper(): FaqHelper
    {
        $helper = $this->createStub(FaqHelper::class);
        foreach (['isEnabled', 'isSchemaEnabled', 'isProductPageEnabled', 'isCategoryPageEnabled', 'isCmsPageEnabled'] as $m) {
            $helper->method($m)->willReturnCallback(fn () => $this->helperFlags[$m] ?? false);
        }
        $helper->method('getConfigValue')->willReturnCallback(fn ($path) => $this->configValues[$path] ?? null);
        $helper->method('getFaqRoute')->willReturnCallback(
            fn () => (string)($this->configValues[FaqHelper::XML_PATH_FAQ_ROUTE] ?? '')
        );

        return $helper;
    }

    protected function storeManager(int $storeId = 1): StoreManagerInterface
    {
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn($storeId);
        $store->method('getBaseUrl')->willReturnCallback(
            fn ($type = UrlInterface::URL_TYPE_LINK) => $type === UrlInterface::URL_TYPE_MEDIA
                ? 'https://shop.test/media/'
                : 'https://shop.test/'
        );
        $manager = $this->createStub(StoreManagerInterface::class);
        $manager->method('getStore')->willReturn($store);

        return $manager;
    }

    /**
     * Each create() returns a new recording collection; calls are logged per collection index.
     */
    protected function itemCollectionFactory(): CollectionFactory
    {
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturnCallback(function () {
            $index = count($this->collections);
            $items = $this->collectionItems[$index] ?? [];
            $this->collectionCalls[$index] = [];
            $select = $this->createStub(Select::class);
            foreach (['joinLeft', 'join', 'where', 'group', 'order'] as $method) {
                $select->method($method)->willReturnCallback(function (...$args) use ($method, $select, $index) {
                    $this->collectionCalls[$index][] = array_merge(['select.' . $method], [$args[0]]);
                    return $select;
                });
            }
            $collection = $this->createStub(Collection::class);
            foreach ([
                'addActiveFilter',
                'addStoreFilter',
                'addProductFilter',
                'addCatalogCategoryFilter',
                'addPageFilter',
                'addCategoryFilter',
                'addFaqCategoryAssignmentFilter',
                'addFieldToFilter',
                'setOrder',
                'setPageSize',
            ] as $method) {
                $collection->method($method)->willReturnCallback(
                    function (...$args) use ($method, $collection, $index) {
                        while ($args !== [] && (end($args) === null || end($args) === true)) {
                            array_pop($args);
                        }
                        $this->collectionCalls[$index][] = array_merge([$method], $args);
                        return $collection;
                    }
                );
            }
            $collection->method('getSelect')->willReturn($select);
            $collection->method('getTable')->willReturnArgument(0);
            $collection->method('getSize')->willReturn(count($items));
            $collection->method('getIterator')->willReturnCallback(fn () => new \ArrayIterator($items));
            $collection->method('getColumnValues')->willReturnCallback(
                fn ($column) => array_map(static fn ($item) => $item->getData($column), $items)
            );
            $this->collections[$index] = $collection;

            return $collection;
        });

        return $factory;
    }

    protected function entity(int $id, array $data = []): DataObject
    {
        return new class (array_merge(['id' => $id, 'item_id' => $id], $data)) extends DataObject {
            public function getId()
            {
                return $this->getData('id');
            }
        };
    }
}
