<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Model\ResourceModel;

use Panth\Faq\Model\ResourceModel\StoreValueRow;
use PHPUnit\Framework\TestCase;

class StoreValueRowTest extends TestCase
{
    private const FIELDS = ['question', 'answer', 'is_active'];

    public function testUseDefaultAcceptsTheListForm(): void
    {
        $this->assertSame(['question', 'answer'], StoreValueRow::useDefaultFields(['question', 'answer', 'bogus'], self::FIELDS));
    }

    public function testUseDefaultAcceptsTheKeyedForm(): void
    {
        $this->assertSame(
            ['answer', 'is_active'],
            StoreValueRow::useDefaultFields(['question' => 0, 'answer' => 1, 'is_active' => '1', 'bogus' => 1], self::FIELDS)
        );
    }

    public function testUseDefaultIgnoresFalseLikeValuesAndNonArrays(): void
    {
        $this->assertSame([], StoreValueRow::useDefaultFields(['question' => '0', 'answer' => false, 'is_active' => ''], self::FIELDS));
        $this->assertSame([], StoreValueRow::useDefaultFields('question', self::FIELDS));
        $this->assertSame([], StoreValueRow::useDefaultFields(null, self::FIELDS));
    }

    public function testBuildKeepsRealOverridesOnly(): void
    {
        $row = StoreValueRow::build(
            self::FIELDS,
            ['question' => 'Store Q', 'answer' => 'A', 'is_active' => 0],
            ['question' => 'Q', 'answer' => 'A', 'is_active' => '1'],
            []
        );

        $this->assertSame(['question' => 'Store Q', 'answer' => null, 'is_active' => 0], $row);
    }

    public function testBuildComparesNumbersAsStrings(): void
    {
        $row = StoreValueRow::build(['is_active'], ['is_active' => 1], ['is_active' => '1'], []);

        $this->assertNull($row['is_active']);
    }

    public function testBuildKeepsAValueWhenTheDefaultIsEmpty(): void
    {
        $row = StoreValueRow::build(['answer'], ['answer' => 'Store A'], ['answer' => null], []);

        $this->assertSame('Store A', $row['answer']);
    }

    public function testBuildTreatsEmptyStringsAndUseDefaultAsInherit(): void
    {
        $row = StoreValueRow::build(self::FIELDS, ['question' => '', 'answer' => 'X'], [], ['answer']);

        $this->assertSame(['question' => null, 'answer' => null, 'is_active' => null], $row);
        $this->assertTrue(StoreValueRow::inheritsEverything($row));
        $this->assertFalse(StoreValueRow::inheritsEverything(['question' => 'x']));
    }

    public function testImplicitScopeMatchesOnlyTheSameStore(): void
    {
        $this->assertTrue(StoreValueRow::isImplicit([StoreValueRow::IMPLICIT_SCOPE_KEY => 1], 1));
        $this->assertFalse(StoreValueRow::isImplicit([StoreValueRow::IMPLICIT_SCOPE_KEY => 1], 2));
        $this->assertFalse(StoreValueRow::isImplicit([], 1));
        $this->assertFalse(StoreValueRow::isImplicit([StoreValueRow::IMPLICIT_SCOPE_KEY => 0], 0));
    }

    public function testKeyedUseDefaultIsDroppedForAFieldChangedAfterLoading(): void
    {
        $fields = StoreValueRow::useDefaultFields(
            ['question' => 1, 'answer' => 1],
            self::FIELDS,
            ['question' => 'Q', 'answer' => 'A'],
            ['question' => 'Changed in code', 'answer' => 'A']
        );

        $this->assertSame(['answer'], $fields);
    }

    public function testListFormIsAlwaysHonoured(): void
    {
        $fields = StoreValueRow::useDefaultFields(
            ['question'],
            self::FIELDS,
            ['question' => 'Q'],
            ['question' => 'Typed in the form']
        );

        $this->assertSame(['question'], $fields);
    }

    public function testBaseRowValuesKeepDefaultsUnlessChangedInCode(): void
    {
        $data = [
            'question' => 'Store Q',
            'answer' => 'New A',
            StoreValueRow::LOADED_VALUES_KEY => ['question' => 'Store Q', 'answer' => 'Store A', 'is_active' => '1'],
        ];

        $values = StoreValueRow::baseRowValues(self::FIELDS, $data, ['question' => 'Q', 'answer' => 'A', 'is_active' => '1']);

        $this->assertSame(['question' => 'Q', 'answer' => 'New A', 'is_active' => '1'], $values);
    }
}
