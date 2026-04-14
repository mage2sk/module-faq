<?php
/**
 * FAQ Category Search Results Interface
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

interface CategorySearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get categories list
     *
     * @return \Panth\Faq\Api\Data\CategoryInterface[]
     */
    public function getItems();

    /**
     * Set categories list
     *
     * @param \Panth\Faq\Api\Data\CategoryInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
