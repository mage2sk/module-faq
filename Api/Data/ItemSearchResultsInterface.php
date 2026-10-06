<?php
/**
 * FAQ Item Search Results Interface
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

interface ItemSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get items list
     *
     * @return \Panth\Faq\Api\Data\ItemInterface[]
     */
    public function getItems();

    /**
     * Set items list
     *
     * @param \Panth\Faq\Api\Data\ItemInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
