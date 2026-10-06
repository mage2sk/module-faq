<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Block;

use Panth\Faq\Block\CustomCss;
use PHPUnit\Framework\TestCase;

class CustomCssTest extends TestCase
{
    use BlockTestTrait;

    public function testEmptyConfigReturnsEmptyString(): void
    {
        $this->assertSame('', (new CustomCss($this->templateContext()))->getCustomCss());
    }

    public function testStripsAngleBracketsAndTrims(): void
    {
        $this->configValues[CustomCss::XML_PATH_CUSTOM_CSS] = "  .faq{color:red}</style><script>x</script>\n";

        $this->assertSame(
            '.faq{color:red}/style>script>x/script>',
            (new CustomCss($this->templateContext()))->getCustomCss()
        );
    }
}
