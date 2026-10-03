<?php
declare(strict_types=1);

namespace Panth\Faq\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Panth\Faq\Model\ResourceModel\Category\CollectionFactory;

class Category implements OptionSourceInterface
{
    protected $collectionFactory;
    protected $options;

    public function __construct(CollectionFactory $collectionFactory)
    {
        $this->collectionFactory = $collectionFactory;
    }

    public function toOptionArray()
    {
        if ($this->options !== null) {
            return $this->options;
        }

        $this->options = [['value' => '', 'label' => __('-- Please Select --')]];

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('is_active', 1);
        $collection->setOrder('name', 'ASC');

        foreach ($collection as $category) {
            $this->options[] = [
                'value' => $category->getId(),
                'label' => $category->getName()
            ];
        }

        return $this->options;
    }
}
