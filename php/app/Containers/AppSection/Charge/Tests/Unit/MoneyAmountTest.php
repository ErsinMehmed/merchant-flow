<?php

namespace App\Containers\AppSection\Charge\Tests\Unit;

use App\Containers\AppSection\Charge\Values\MoneyAmount;
use App\Ship\Parents\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class MoneyAmountTest extends TestCase
{
    #[DataProvider('inputs')]
    public function test_converts_typed_amounts_to_minor_units(string $input, ?int $expected): void
    {
        $this->assertSame($expected, MoneyAmount::toMinorUnits($input, 'USD'));
    }

    /** @return iterable<string, array{string, ?int}> */
    public static function inputs(): iterable
    {
        yield 'whole' => ['12', 1200];
        yield 'one decimal' => ['12.5', 1250];
        yield 'two decimals' => ['12.50', 1250];
        yield 'cent' => ['0.01', 1];
        yield 'surrounding spaces' => [' 7.25 ', 725];
        yield 'maximum' => ['999999.99', 99_999_999];
        yield 'float trap' => ['0.29', 29];
        yield 'three decimals' => ['1.005', null];
        yield 'negative' => ['-5', null];
        yield 'thousands separator' => ['1,000', null];
        yield 'exponent' => ['1e3', null];
        yield 'empty' => ['', null];
        yield 'too large' => ['1000000', null];
    }

    public function test_formats_minor_units_with_the_currency(): void
    {
        $this->assertSame('$1,234.50', MoneyAmount::format(123_450, 'USD'));
        $this->assertSame('€0.99', MoneyAmount::format(99, 'EUR'));
    }
}
