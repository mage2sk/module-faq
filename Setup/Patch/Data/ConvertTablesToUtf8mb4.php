<?php
/**
 * Convert FAQ tables to UTF8MB4 for emoji support
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class ConvertTablesToUtf8mb4 implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $tables = [
            'panth_faq_category',
            'panth_faq_item',
            'panth_faq_category_store',
            'panth_faq_item_store',
            'panth_faq_item_product',
            'panth_faq_item_catalog_category',
            'panth_faq_item_page',
            'panth_faq_item_faq_category'
        ];

        $connection = $this->moduleDataSetup->getConnection();

        foreach ($tables as $tableName) {
            $fullTableName = $this->moduleDataSetup->getTable($tableName);

            // Check if table exists before converting
            if ($connection->isTableExists($fullTableName)) {
                try {
                    // Convert table to utf8mb4
                    $connection->query(
                        "ALTER TABLE {$fullTableName} CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
                    );
                } catch (\Exception $e) {
                    // Log error but don't fail - table might already be utf8mb4
                    continue;
                }
            }
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }
}
