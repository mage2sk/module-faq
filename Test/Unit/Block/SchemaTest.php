<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Block;

use Magento\Framework\Registry;
use Panth\Faq\Block\Schema;
use Panth\Faq\Helper\Data as FaqHelper;
use PHPUnit\Framework\TestCase;

class SchemaTest extends TestCase
{
    use BlockTestTrait;

    private array $registry = [];

    private function block(): Schema
    {
        $registry = $this->createStub(Registry::class);
        $registry->method('registry')->willReturnCallback(fn ($key) => $this->registry[$key] ?? null);

        return new Schema(
            $this->templateContext(),
            $this->itemCollectionFactory(),
            $registry,
            $this->faqHelper(),
            $this->storeManager(1)
        );
    }

    private function enable(): void
    {
        $this->helperFlags['isEnabled'] = true;
        $this->helperFlags['isSchemaEnabled'] = true;
    }

    private function baseCalls(): array
    {
        return [['addActiveFilter'], ['addStoreFilter', 1], ['setOrder', 'sort_order', 'ASC']];
    }

    public function testIsEnabledNeedsBothFlags(): void
    {
        $block = $this->block();
        $this->helperFlags['isEnabled'] = true;
        $this->assertFalse($block->isEnabled());
        $this->helperFlags['isSchemaEnabled'] = true;
        $this->assertTrue($block->isEnabled());
    }

    public function testCurrentFaqItemTakesPriority(): void
    {
        $this->registry = ['current_faq_item' => $this->entity(8), 'current_product' => $this->entity(3)];
        $block = $this->block();

        $collection = $block->getCurrentPageFaqs();

        $this->assertSame($collection, $block->getCurrentPageFaqs());
        $this->assertSame(
            array_merge($this->baseCalls(), [['addFieldToFilter', 'main_table.item_id', 8]]),
            $this->collectionCalls[0]
        );
    }

    public function testProductPageScopeWithLimit(): void
    {
        $this->registry = ['current_product' => $this->entity(3), 'current_category' => $this->entity(4)];
        $this->helperFlags['isProductPageEnabled'] = true;
        $this->configValues[FaqHelper::XML_PATH_PRODUCT_LIMIT] = '2';

        $this->block()->getCurrentPageFaqs();

        $this->assertSame(
            array_merge($this->baseCalls(), [['addProductFilter', 3], ['setPageSize', 2]]),
            $this->collectionCalls[0]
        );
    }

    public function testDisabledProductScopeMatchesNothing(): void
    {
        $this->registry = ['current_product' => $this->entity(3), 'current_category' => $this->entity(4)];

        $this->block()->getCurrentPageFaqs();

        $this->assertSame(
            array_merge($this->baseCalls(), [['addFieldToFilter', 'main_table.item_id', 0]]),
            $this->collectionCalls[0]
        );
    }

    public function testCategoryScope(): void
    {
        $this->registry = ['current_category' => $this->entity(4)];
        $this->helperFlags['isCategoryPageEnabled'] = true;
        $this->configValues[FaqHelper::XML_PATH_CATEGORY_LIMIT] = '0';

        $this->block()->getCurrentPageFaqs();

        $this->assertSame(array_merge($this->baseCalls(), [['addCatalogCategoryFilter', 4]]), $this->collectionCalls[0]);
    }

    public function testCmsPageScope(): void
    {
        $this->registry = ['cms_page' => $this->entity(6)];
        $this->helperFlags['isCmsPageEnabled'] = true;

        $this->block()->getCurrentPageFaqs();

        $this->assertSame(array_merge($this->baseCalls(), [['addPageFilter', 6]]), $this->collectionCalls[0]);
    }

    public function testFaqCategoryAndListingPages(): void
    {
        $this->fullActionName = 'faq_category_view';
        $this->params['id'] = '5';
        $this->block()->getCurrentPageFaqs();
        $this->assertSame(array_merge($this->baseCalls(), [['addCategoryFilter', 5]]), $this->collectionCalls[0]);

        $this->collections = [];
        $this->fullActionName = 'faq_index_index';
        $this->block()->getCurrentPageFaqs();
        $this->assertSame(array_merge($this->baseCalls(), [['addFaqCategoryAssignmentFilter']]), $this->collectionCalls[0]);

        $this->collections = [];
        $this->fullActionName = 'faq_category_view';
        $this->params = [];
        $this->block()->getCurrentPageFaqs();
        $this->assertSame(
            array_merge($this->baseCalls(), [['addFieldToFilter', 'main_table.item_id', 0]]),
            $this->collectionCalls[0]
        );
    }

    public function testSchemaDataIsNullWhenDisabledOrEmpty(): void
    {
        $this->assertNull($this->block()->getSchemaData());

        $this->enable();
        $this->assertNull($this->block()->getSchemaData());
    }

    public function testSchemaDataBuildsFaqPageJson(): void
    {
        $this->enable();
        $this->fullActionName = 'faq_index_index';
        $this->configValues[FaqHelper::XML_PATH_SCHEMA_MAX_QUESTIONS] = '2';
        $this->collectionItems[0] = [
            $this->entity(1, ['question' => '<b>Shipping?</b>', 'answer' => '<p>Free &amp; fast</p>']),
            $this->entity(2, ['question' => 'Skip me', 'answer' => '<p> </p>']),
            $this->entity(3, ['question' => '', 'answer' => 'No question']),
            $this->entity(4, ['question' => 'Returns </script>?', 'answer' => '30 days']),
            $this->entity(5, ['question' => 'Over limit', 'answer' => 'x']),
        ];

        $json = $this->block()->getSchemaData();
        $data = json_decode((string)$json, true);

        $this->assertSame('https://schema.org', $data['@context']);
        $this->assertSame('FAQPage', $data['@type']);
        $this->assertCount(2, $data['mainEntity']);
        $this->assertSame('Shipping?', $data['mainEntity'][0]['name']);
        $this->assertSame('Free & fast', $data['mainEntity'][0]['acceptedAnswer']['text']);
        $this->assertSame('Returns ?', $data['mainEntity'][1]['name']);
        $this->assertStringNotContainsString('<', (string)$json);
        $this->assertStringNotContainsString('&', (string)$json);
        $this->assertStringContainsString('\\' . 'u0026', (string)$json);
    }

    public function testSchemaDataIsNullWhenNoUsableQuestions(): void
    {
        $this->enable();
        $this->fullActionName = 'faq_index_index';
        $this->collectionItems[0] = [$this->entity(1, ['question' => 'Q', 'answer' => '<br>'])];

        $this->assertNull($this->block()->getSchemaData());
    }

    public function testIdentitiesComeFromCurrentItems(): void
    {
        $this->fullActionName = 'faq_index_index';
        $this->collectionItems[0] = [$this->entity(1), $this->entity(2)];

        $this->assertSame(['panth_faq_item_1', 'panth_faq_item_2'], $this->block()->getIdentities());
    }
}
