<?php

namespace Tests\Unit\Pricing;

use App\Domain\Pricing\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    /** @return array<string, array{int, int, int}> */
    public static function cases(): array
    {
        return [
            'exact' => [49_990, 1_000, 4_999],
            'half cent rounds up' => [5, 1_000, 1],          // 0.5
            'below half rounds down' => [4, 1_000, 0],       // 0.4
            'above half rounds up' => [6, 1_000, 1],         // 0.6
            'half cent on odd amount' => [12_345, 1_000, 1_235], // 1234.5
            'loyalty 3%' => [3_499, 300, 105],               // 104.97
            'zero amount' => [0, 1_000, 0],
            'zero bps' => [12_345, 0, 0],
            'full discount' => [12_345, 10_000, 12_345],
        ];
    }

    #[DataProvider('cases')]
    public function test_percent_of_rounds_half_up_in_integers(int $cents, int $bps, int $expected): void
    {
        $this->assertSame($expected, Money::percentOf($cents, $bps));
    }

    public function test_negative_amounts_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::percentOf(-1, 1_000);
    }
}
