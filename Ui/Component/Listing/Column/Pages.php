<?php
/**
 * FAQ CMS Pages Column Renderer
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\App\ResourceConnection;

class Pages extends Column
{
    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param ResourceConnection $resourceConnection
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        ResourceConnection $resourceConnection,
        array $components = [],
        array $data = []
    ) {
        $this->resourceConnection = $resourceConnection;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            $connection = $this->resourceConnection->getConnection();

            foreach ($dataSource['data']['items'] as &$item) {
                $itemId = $item['item_id'];

                // Get assigned CMS pages
                $select = $connection->select()
                    ->from(
                        ['fp' => $connection->getTableName('panth_faq_item_page')],
                        ['page_id']
                    )
                    ->joinLeft(
                        ['cp' => $connection->getTableName('cms_page')],
                        'fp.page_id = cp.page_id',
                        ['title', 'identifier']
                    )
                    ->where('fp.item_id = ?', $itemId)
                    ->limit(5);

                $pages = $connection->fetchAll($select);

                if (!empty($pages)) {
                    $pageLabels = [];
                    foreach ($pages as $page) {
                        $title = $page['title'] ?: $page['identifier'] ?: 'Page';
                        $pageLabels[] = sprintf(
                            '<span title="ID: %d - %s" style="display: inline-block; padding: 3px 8px; margin: 2px; background: #445a54; color: white; border-radius: 3px; font-size: 11px; cursor: help;">%s</span>',
                            $page['page_id'],
                            $this->escapeHtml($title),
                            $this->escapeHtml($this->truncate($title, 20))
                        );
                    }
                    $item['cms_pages'] = implode(' ', $pageLabels);

                    if (count($pages) >= 5) {
                        $item['cms_pages'] .= ' <span style="color: #666; font-size: 11px;">...</span>';
                    }
                } else {
                    $item['cms_pages'] = '<span style="color: #999; font-style: italic;">None</span>';
                }
            }
        }

        return $dataSource;
    }

    /**
     * Truncate string
     *
     * @param string $string
     * @param int $length
     * @return string
     */
    protected function truncate($string, $length)
    {
        if (strlen($string) > $length) {
            return substr($string, 0, $length) . '...';
        }
        return $string;
    }

    /**
     * Escape HTML
     *
     * @param string $string
     * @return string
     */
    protected function escapeHtml($string)
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}
