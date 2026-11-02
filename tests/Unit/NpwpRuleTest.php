<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Rules\Npwp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NpwpRuleTest extends TestCase
{
    private function passes(string $value): bool
    {
        $failed = false;
        (new Npwp)->validate('nomor_npwp', $value, function () use (&$failed): void {
            $failed = true;
        });

        return ! $failed;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validNpwpNumbers(): array
    {
        return [
            '15 digits formatted standard' => ['01.234.567.8-901.000'],
            '15 digits another sample' => ['31.123.456.7-890.000'],
            '15 raw digits' => ['012345678901000'],
            '16 raw digits' => ['0123456789010001'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidNpwpNumbers(): array
    {
        return [
            'letters only' => ['abcdefghijklmno'],
            'mixed letters and numbers' => ['01.234.567.8-90A.000'],
            'too short 5 digits' => ['12345'],
            'too short 14 digits' => ['01234567890123'],
            'too long 17 digits' => ['01234567890123456'],
            'incorrect formatting punctuation' => ['01-234-567-8-901-000'],
            'empty string' => [''],
        ];
    }

    #[DataProvider('validNpwpNumbers')]
    public function test_accepts_valid_npwp_formats(string $value): void
    {
        $this->assertTrue($this->passes($value));
    }

    #[DataProvider('invalidNpwpNumbers')]
    public function test_rejects_invalid_npwp_formats(string $value): void
    {
        $this->assertFalse($this->passes($value));
    }
}
