<?php
/**
 * Default State Source Model
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class DefaultState implements OptionSourceInterface
{
    const STATE_COLLAPSED = 0;
    const STATE_EXPANDED = 1;
    const STATE_FIRST_EXPANDED = 2;

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::STATE_COLLAPSED, 'label' => __('All Collapsed')],
            ['value' => self::STATE_EXPANDED, 'label' => __('All Expanded')],
            ['value' => self::STATE_FIRST_EXPANDED, 'label' => __('First Item Expanded')]
        ];
    }
}
