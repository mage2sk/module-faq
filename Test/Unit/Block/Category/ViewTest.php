<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Block\Category;

use Magento\Framework\Exception\NoSuchEntityException;
use Panth\Faq\Api\CategoryRepositoryInterface;
use Panth\Faq\Block\Category\View;
use Panth\Faq\Helper\Data as FaqHelper;
use Panth\Faq\Test\Unit\Block\BlockTestTrait;
use Panth\Faq\Test\Unit\EntityTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ViewTest extends TestCase
{
    use BlockTestTrait;
    use EntityTrait;

    private array $categories = [];

    private function block(): View
    {
        $repository = $this->createStub(CategoryRepositoryInterface::class);
        $repository->method('getById')->willReturnCallback(function ($id) {
            if (!isset($this->categories[$id])) {
                throw new NoSuchEntityException(__('missing'));
            }
            return $this->categories[$id];
        });

        return new View(
            $this->templateContext(),
            $repository,
            $this->itemCollectionFactory(),
            $this->faqHelper(),
            $this->storeManager(2),
            $this->scopeConfig()
        );
    }

    public function testCategoryFromRequest(): void
    {
        $this->assertNull($this->block()->getCategory());

        $this->params['id'] = '8';
        $this->assertNull($this->block()->getCategory());

        $category = $this->newCategory(['category_id' => 8]);
        $this->categories[8] = $category;
        $this->assertSame($category, $this->block()->getCategory());
    }

    public static function iconProvider(): array
    {
        return [
            'png' => ['icon.png', 'https://shop.test/media/panth/faq/category/icon.png'],
            'path is reduced to basename' => ['../../etc/env.JPEG', 'https://shop.test/media/panth/faq/category/env.JPEG'],
            'spaces are encoded' => ['my icon.gif', 'https://shop.test/media/panth/faq/category/my%20icon.gif'],
            'svg is refused' => ['icon.svg', null],
            'empty' => ['', null],
        ];
    }

    #[DataProvider('iconProvider')]
    public function testIconUrl(string $icon, ?string $expected): void
    {
        $this->assertSame($expected, $this->block()->getIconUrl($this->newCategory(['icon' => $icon])));
    }

    public function testIconUrlForMissingCategory(): void
    {
        $this->assertNull($this->block()->getIconUrl(null));
    }

    public function testFaqItemsFilteredByCategory(): void
    {
        $this->block()->getFaqItems();
        $this->assertSame([], $this->collectionCalls[0]);

        $this->collections = [];
        $this->categories[8] = $this->newCategory(['category_id' => 8]);
        $this->params['id'] = '8';
        $this->block()->getFaqItems();

        $this->assertSame(
            [['addActiveFilter'], ['addStoreFilter', 2], ['addCategoryFilter', 8], ['setOrder', 'sort_order', 'ASC']],
            $this->collectionCalls[0]
        );
    }

    public function testBackUrlAndFlags(): void
    {
        $block = $this->block();
        $this->assertSame('https://shop.test/faq', $block->getBackUrl());
        $this->configValues['panth_faq/general/faq_route'] = 'kb';
        $this->assertSame('https://shop.test/kb', $block->getBackUrl());

        foreach ([
            'isSearchEnabled' => FaqHelper::XML_PATH_SHOW_SEARCH,
            'showCategoryDescription' => FaqHelper::XML_PATH_SHOW_CATEGORY_DESC,
            'showViewCount' => FaqHelper::XML_PATH_SHOW_VIEW_COUNT,
            'isHelpfulVotingEnabled' => FaqHelper::XML_PATH_ENABLE_HELPFUL_VOTING,
            'isDefaultOpen' => FaqHelper::XML_PATH_DEFAULT_OPEN_FAQS,
        ] as $method => $path) {
            $this->assertFalse($block->$method(), $method);
            $this->configValues[$path] = '1';
            $this->assertTrue($block->$method(), $method);
        }
        $this->assertSame(['panth_faq_item', 'panth_faq_category'], $block->getIdentities());
    }
}
