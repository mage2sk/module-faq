<?php
declare(strict_types=1);

namespace Panth\Faq\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * FAQ Item grid action column.
 *
 * The "View" action was removed in 1.1.0 — with per-store URL keys it's
 * impossible to pick the right storefront link from the admin grid in
 * a way that matches what the merchant currently has selected. The View
 * link now lives on the entity edit page header, where it can use the
 * scope dropdown's selected store to pick the correct URL.
 */
class ItemActions extends Column
{
    public const URL_PATH_EDIT = 'faq/item/edit';
    public const URL_PATH_DELETE = 'faq/item/delete';

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        protected UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                if (!isset($item['item_id'])) {
                    continue;
                }
                $item[$this->getData('name')] = [
                    'edit' => [
                        'href' => $this->urlBuilder->getUrl(
                            static::URL_PATH_EDIT,
                            ['item_id' => $item['item_id']]
                        ),
                        'label' => __('Edit'),
                    ],
                    'delete' => [
                        'href' => $this->urlBuilder->getUrl(
                            static::URL_PATH_DELETE,
                            ['item_id' => $item['item_id']]
                        ),
                        'label' => __('Delete'),
                        'confirm' => [
                            'title' => __('Delete FAQ Item'),
                            'message' => __('Are you sure you want to delete this FAQ item?'),
                        ],
                    ],
                ];
            }
        }
        return $dataSource;
    }
}
