<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Model\ResourceModel;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;

trait SelectRecorderTrait
{
    protected array $sql = [];

    protected function recordingSelect(): Select
    {
        $select = $this->createStub(Select::class);
        foreach (['join', 'joinLeft', 'where', 'columns', 'group', 'order'] as $method) {
            $select->method($method)->willReturnCallback(function (...$args) use ($method, $select) {
                while ($args !== [] && end($args) === null) {
                    array_pop($args);
                }
                $this->sql[] = array_merge([$method], array_map(
                    static fn ($a) => $a instanceof \Zend_Db_Expr ? (string)$a : $a,
                    $args
                ));
                return $select;
            });
        }

        return $select;
    }

    protected function quotingConnection(): AdapterInterface
    {
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('quoteInto')->willReturnCallback(
            fn ($text, $value) => str_replace('?', "'" . $value . "'", $text)
        );

        return $connection;
    }

    protected function sqlCalls(string $method): array
    {
        return array_values(array_filter($this->sql, static fn ($call) => $call[0] === $method));
    }

    protected function renderFilters(object $collection): void
    {
        (new \ReflectionMethod($collection, '_renderFiltersBefore'))->invoke($collection);
    }
}
