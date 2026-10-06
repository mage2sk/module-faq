<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Model\ResourceModel;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;

trait FakeConnectionTrait
{
    protected array $queries = [];
    protected array $writes = [];
    protected array $rowResults = [];
    protected array $oneResults = [];
    protected array $colResults = [];

    protected function fakeConnection(): AdapterInterface
    {
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturnCallback(function () {
            $index = count($this->queries);
            $this->queries[$index] = ['from' => null, 'where' => [], 'join' => null];
            $select = $this->createStub(Select::class);
            $select->method('from')->willReturnCallback(function ($table, $cols = '*') use ($select, $index) {
                $this->queries[$index]['from'] = [$table, $cols];
                return $select;
            });
            $select->method('joinLeft')->willReturnCallback(function ($table, $cond) use ($select, $index) {
                $this->queries[$index]['join'] = [$table, $cond];
                return $select;
            });
            $select->method('where')->willReturnCallback(function ($cond, $value = null) use ($select, $index) {
                $this->queries[$index]['where'][] = [$cond, $value];
                return $select;
            });
            $select->method('limit')->willReturnSelf();
            return $select;
        });
        $connection->method('fetchRow')->willReturnCallback(fn () => array_shift($this->rowResults) ?? false);
        $connection->method('fetchOne')->willReturnCallback(fn () => array_shift($this->oneResults) ?? false);
        $connection->method('fetchCol')->willReturnCallback(fn () => array_shift($this->colResults) ?? []);
        foreach (['delete', 'insertMultiple', 'insertOnDuplicate'] as $method) {
            $connection->method($method)->willReturnCallback(function (...$args) use ($method) {
                $this->writes[] = array_merge([$method], $args);
                return 1;
            });
        }

        return $connection;
    }

    protected function invoke(object $object, string $method, ...$args)
    {
        return (new \ReflectionMethod($object, $method))->invoke($object, ...$args);
    }
}
