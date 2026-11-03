<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\NpwpHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NpwpHelperTest extends TestCase
{
    /**
     * Keep in sync with `normalizeNpwp()` in resources/js/lib/validators.js.
     *
     * @return array<string, array{string, string|null}>
     */
    public static function cases(): array
    {
        return [
            '15 raw digits to formatted' => ['012345678901000', '01.234.567.8-901.000'],
            '15 digits already formatted' => ['01.234.567.8-901.000', '01.234.567.8-901.000'],
            '15 digits with whitespace' => ['  01.234.567.8-901.000  ', '01.234.567.8-901.000'],
            '15 raw digits with whitespace' => ['  012345678901000  ', '01.234.567.8-901.000'],
            '16 raw digits' => ['0123456789010001', '0123456789010001'],
            'blank input' => ['   ', null],
            'invalid digits left for rule validation' => ['12345', '12345'],
            'letters left for rule validation' => ['01.234.567.8-ABC.000', '01.234.567.8-ABC.000'],
        ];
    }

    #[DataProvider('cases')]
    public function test_normalizes_npwp(string $input, ?string $expected): void
    {
        $this->assertSame($expected, NpwpHelper::normalize($input));
    }

    public function test_null_becomes_null_and_normalizing_twice_is_stable(): void
    {
        $this->assertNull(NpwpHelper::normalize(null));

        foreach (self::cases() as [$input]) {
            $once = NpwpHelper::normalize($input);
            $this->assertSame($once, NpwpHelper::normalize($once));
        }
    }
}
