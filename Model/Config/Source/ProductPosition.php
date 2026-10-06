<?php
declare(strict_types=1);

namespace Panth\Faq\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ProductPosition implements OptionSourceInterface
{
    public const POSITION_TAB = 'tab';
    public const POSITION_BELOW_TABS = 'below_tabs';
    public const POSITION_PRODUCT_INFO = 'product_info';

    public function toOptionArray()
    {
        return [
            ['value' => self::POSITION_TAB, 'label' => __('FAQ tab in the product tabs (recommended)')],
            ['value' => self::POSITION_BELOW_TABS, 'label' => __('Full-width section below the product tabs')],
            ['value' => self::POSITION_PRODUCT_INFO, 'label' => __('Product info column, under Add to Cart (old style)')],
        ];
    }

    public static function normalize($value): string
    {
        $value = (string) $value;
        if ($value === self::POSITION_BELOW_TABS || $value === self::POSITION_PRODUCT_INFO) {
            return $value;
        }
        return self::POSITION_TAB;
    }
}
