<?php
declare(strict_types=1);

namespace Panth\Faq\Model;

use Magento\Framework\Api\SearchResults;
use Panth\Faq\Api\Data\CategorySearchResultsInterface;

class CategorySearchResults extends SearchResults implements CategorySearchResultsInterface
{
}
