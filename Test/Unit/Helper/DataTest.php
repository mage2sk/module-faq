<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Helper;

use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Filter\Template as TemplateFilter;
use Magento\Store\Model\ScopeInterface;
use Panth\Faq\Helper\Data;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DataTest extends TestCase
{
    private array $flags = [];
    private array $values = [];
    private FilterProvider $filterProvider;

    private function helper(): Data
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            fn ($path, $scope = null, $store = null) => (bool)($this->flags[$path] ?? false)
        );
        $scopeConfig->method('getValue')->willReturnCallback(
            fn ($path, $scope = null, $store = null) => $this->values[$path] ?? null
        );
        $context = $this->createStub(Context::class);
        $context->method('getScopeConfig')->willReturn($scopeConfig);

        return new Data($context, $this->filterProvider);
    }

    protected function setUp(): void
    {
        $this->filterProvider = $this->createStub(FilterProvider::class);
    }

    public function testProductPositionDefaultsToTab(): void
    {
        $this->assertSame('tab', $this->helper()->getProductPosition());
        $this->values[Data::XML_PATH_PRODUCT_POSITION] = 'after_additional';
        $this->assertSame('tab', $this->helper()->getProductPosition());
    }

    public function testProductPositionReturnsConfiguredValue(): void
    {
        $this->values[Data::XML_PATH_PRODUCT_POSITION] = 'below_tabs';
        $this->assertSame('below_tabs', $this->helper()->getProductPosition());
        $this->values[Data::XML_PATH_PRODUCT_POSITION] = 'product_info';
        $this->assertSame('product_info', $this->helper()->getProductPosition(2));
    }

    public function testRenderRichTextReturnsEmptyStringForNullAndEmpty(): void
    {
        $provider = $this->createMock(FilterProvider::class);
        $provider->expects($this->never())->method('getPageFilter');
        $this->filterProvider = $provider;
        $helper = $this->helper();

        $this->assertSame('', $helper->renderRichText(null));
        $this->assertSame('', $helper->renderRichText(''));
    }

    public function testRenderRichTextUsesPageFilter(): void
    {
        $filter = $this->createMock(TemplateFilter::class);
        $filter->expects($this->once())->method('filter')->with('{{var x}}')->willReturn('<p>rendered</p>');
        $this->filterProvider = $this->createStub(FilterProvider::class);
        $this->filterProvider->method('getPageFilter')->willReturn($filter);

        $this->assertSame('<p>rendered</p>', $this->helper()->renderRichText('{{var x}}'));
    }

    public function testRenderRichTextFallsBackToRawContentWhenFilterFails(): void
    {
        $filter = $this->createStub(TemplateFilter::class);
        $filter->method('filter')->willThrowException(new \RuntimeException('broken directive'));
        $this->filterProvider = $this->createStub(FilterProvider::class);
        $this->filterProvider->method('getPageFilter')->willReturn($filter);

        $this->assertSame('<b>raw</b>', $this->helper()->renderRichText('<b>raw</b>'));
    }

    public function testIsEnabledReadsGeneralFlag(): void
    {
        $this->assertFalse($this->helper()->isEnabled());
        $this->flags[Data::XML_PATH_ENABLED] = true;
        $this->assertTrue($this->helper()->isEnabled());
    }

    public function testGetFaqRouteCastsToString(): void
    {
        $this->assertSame('', $this->helper()->getFaqRoute());
        $this->values[Data::XML_PATH_FAQ_ROUTE] = 'help-center';
        $this->assertSame('help-center', $this->helper()->getFaqRoute());
    }

    public function testGetConfigValuePassesStoreScope(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects($this->once())->method('getValue')
            ->with('panth_faq/seo/max_questions', ScopeInterface::SCOPE_STORE, 3)
            ->willReturn('7');
        $context = $this->createStub(Context::class);
        $context->method('getScopeConfig')->willReturn($scopeConfig);
        $helper = new Data($context, $this->filterProvider);

        $this->assertSame('7', $helper->getConfigValue('panth_faq/seo/max_questions', 3));
    }

    public static function pageFlagProvider(): array
    {
        return [
            'product' => ['isProductPageEnabled', Data::XML_PATH_PRODUCT_ENABLED],
            'category' => ['isCategoryPageEnabled', Data::XML_PATH_CATEGORY_ENABLED],
            'cms' => ['isCmsPageEnabled', Data::XML_PATH_CMS_ENABLED],
        ];
    }

    #[DataProvider('pageFlagProvider')]
    public function testPageFlagsRequireModuleEnabled(string $method, string $path): void
    {
        $this->flags[$path] = true;
        $this->assertFalse($this->helper()->$method(), 'module disabled must win');

        $this->flags[Data::XML_PATH_ENABLED] = true;
        $this->assertTrue($this->helper()->$method());

        $this->flags[$path] = false;
        $this->assertFalse($this->helper()->$method());
    }

    public function testSchemaFlagIsIndependentOfGeneralFlag(): void
    {
        $this->flags[Data::XML_PATH_ENABLE_SCHEMA] = true;
        $this->assertTrue($this->helper()->isSchemaEnabled());
        $this->flags[Data::XML_PATH_ENABLE_SCHEMA] = false;
        $this->assertFalse($this->helper()->isSchemaEnabled());
    }
}
