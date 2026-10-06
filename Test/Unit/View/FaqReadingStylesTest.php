<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\View;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FaqReadingStylesTest extends TestCase
{
    private const SCOPE = ':is(.faq-index-index, .faq-category-view) .column.main';

    private function read(string $relative): string
    {
        $path = dirname(__DIR__, 3) . '/' . $relative;
        $this->assertTrue(is_file($path), $relative . ' is missing');

        return (string) file_get_contents($path);
    }

    public static function stylesheetProvider(): array
    {
        return [
            'hyva' => ['view/frontend/web/css/faq.css'],
            'luma' => ['view/frontend/web/css/source/_module.less'],
        ];
    }

    #[DataProvider('stylesheetProvider')]
    public function testTextContainerIsSevenSixtyWide(string $file): void
    {
        $this->assertStringContainsString(
            self::SCOPE . " :is(.faq-wrapper, .faq-category-page) {\n    margin-left: auto;\n"
            . "    margin-right: auto;\n    max-width: 760px;",
            $this->read($file)
        );
    }

    #[DataProvider('stylesheetProvider')]
    public function testHeadingAndBodyScale(string $file): void
    {
        $css = $this->read($file);

        $this->assertStringContainsString(self::SCOPE . " h1 {\n    font-size: 36px;", $css);
        $this->assertStringContainsString(self::SCOPE . " h2 {\n    font-size: 28px;", $css);
        $this->assertStringContainsString(
            self::SCOPE . " :is(.faq-item-header, .faq-cat-item) h3 {\n    font-size: 18px;",
            $css
        );
        $this->assertStringContainsString(
            self::SCOPE . " :is(.faq-answer, .faq-item .prose, .faq-cat-item .prose) {\n"
            . "    font-size: 16px;\n    line-height: 1.5;",
            $css
        );
        $this->assertStringContainsString(self::SCOPE . " .faq-category-section {\n    margin-bottom: 64px;", $css);
    }

    #[DataProvider('stylesheetProvider')]
    public function testPhoneScale(string $file): void
    {
        $css = $this->read($file);
        $mobile = substr($css, (int) strrpos($css, '@media (max-width: 767px) {'));

        $this->assertStringContainsString(self::SCOPE . " h1 {\n        font-size: 28px;", $mobile);
        $this->assertStringContainsString(self::SCOPE . " h2 {\n        font-size: 24px;", $mobile);
        $this->assertStringContainsString(
            self::SCOPE . " .faq-category-section {\n        margin-bottom: 40px;",
            $mobile
        );
    }

    #[DataProvider('stylesheetProvider')]
    public function testSearchButtonUsesButtonText(string $file): void
    {
        $this->assertStringContainsString(
            self::SCOPE . " .faq-search button {\n    font-size: 15px !important;\n    font-weight: 600 !important;",
            $this->read($file)
        );
    }

    public function testHyvaCategoryTemplateHasTheReadingHook(): void
    {
        $this->assertStringContainsString(
            '<div class="faq-category-page max-w-3xl mx-auto py-4">',
            $this->read('view/frontend/templates/hyva/category/view.phtml')
        );
    }

    public function testBothStylesheetsShipTheSameReadingBlock(): void
    {
        $block = static function (string $css): string {
            return substr($css, (int) strpos($css, self::SCOPE . ' :is(.faq-wrapper, .faq-category-page) {'));
        };

        $this->assertSame(
            $block($this->read('view/frontend/web/css/faq.css')),
            $block($this->read('view/frontend/web/css/source/_module.less'))
        );
    }
}
