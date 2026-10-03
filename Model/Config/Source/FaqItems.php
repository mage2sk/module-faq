<?php
declare(strict_types=1);

namespace Panth\Faq\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Panth\Faq\Model\ResourceModel\Item\CollectionFactory;

class FaqItems implements OptionSourceInterface
{
    protected $collectionFactory;

    public function __construct(
        CollectionFactory $collectionFactory
    ) {
        $this->collectionFactory = $collectionFactory;
    }

    public function toOptionArray()
    {
        $options = [];
        $collection = $this->collectionFactory->create();
        $collection->addActiveFilter()
            ->setOrder('sort_order', 'ASC');

        foreach ($collection as $item) {
            $options[] = [
                'value' => $item->getId(),
                'label' => $item->getQuestion()
            ];
        }

        return $options;
    }
}
