<?php
/**
 * FAQ Item Search Results
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Model;

use Magento\Framework\Api\SearchResults;
use Panth\Faq\Api\Data\ItemSearchResultsInterface;

class ItemSearchResults extends SearchResults implements ItemSearchResultsInterface
{
}
