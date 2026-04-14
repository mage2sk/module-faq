<?php
/**
 * Product Position Source Model
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ProductPosition implements OptionSourceInterface
{
    const POSITION_TAB = 'tab';
    const POSITION_AFTER_DESCRIPTION = 'after_description';
    const POSITION_AFTER_ADDITIONAL = 'after_additional';
    const POSITION_BEFORE_RELATED = 'before_related';

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::POSITION_TAB, 'label' => __('As Tab')],
            ['value' => self::POSITION_AFTER_DESCRIPTION, 'label' => __('After Description')],
            ['value' => self::POSITION_AFTER_ADDITIONAL, 'label' => __('After Additional Information')],
            ['value' => self::POSITION_BEFORE_RELATED, 'label' => __('Before Related Products')]
        ];
    }
}
