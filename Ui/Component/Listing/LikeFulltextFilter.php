<?php
declare(strict_types=1);

namespace Panth\Faq\Ui\Component\Listing;

use Magento\Framework\Api\Filter;
use Magento\Framework\Data\Collection;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\View\Element\UiComponent\DataProvider\FilterApplierInterface;

class LikeFulltextFilter implements FilterApplierInterface
{
    private const COLUMNS = ['main_table.question', 'main_table.answer', 'main_table.url_key'];

    public function apply(Collection $collection, Filter $filter)
    {
        if (!$collection instanceof AbstractDb) {
            return;
        }
        $value = $filter->getValue();
        $value = is_scalar($value) ? trim((string)$value) : '';
        if ($value === '') {
            return;
        }
        $like = '%' . addcslashes(mb_substr($value, 0, 200), '\%_') . '%';
        $connection = $collection->getConnection();
        $conditions = [];
        foreach (self::COLUMNS as $column) {
            $conditions[] = $connection->quoteInto($column . ' LIKE ?', $like);
        }
        $collection->getSelect()->where(implode(' OR ', $conditions));
    }
}
