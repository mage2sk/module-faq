<?php
declare(strict_types=1);

namespace Panth\Faq\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class ConvertTablesToUtf8mb4 implements DataPatchInterface
{
    private $moduleDataSetup;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
    }

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

            if ($connection->isTableExists($fullTableName)) {
                try {
                    $connection->query(
                        "ALTER TABLE {$fullTableName} CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
                    );
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    public static function getDependencies()
    {
        return [];
    }

    public function getAliases()
    {
        return [];
    }
}
