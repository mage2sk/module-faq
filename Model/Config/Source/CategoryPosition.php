<?php
/**
 * Category Position Source Model
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class CategoryPosition implements OptionSourceInterface
{
    const POSITION_TOP = 'top';
    const POSITION_BOTTOM = 'bottom';
    const POSITION_SIDEBAR_TOP = 'sidebar_top';
    const POSITION_SIDEBAR_BOTTOM = 'sidebar_bottom';

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::POSITION_TOP, 'label' => __('Top of Content')],
            ['value' => self::POSITION_BOTTOM, 'label' => __('Bottom of Content')],
            ['value' => self::POSITION_SIDEBAR_TOP, 'label' => __('Top of Sidebar')],
            ['value' => self::POSITION_SIDEBAR_BOTTOM, 'label' => __('Bottom of Sidebar')]
        ];
    }
}
