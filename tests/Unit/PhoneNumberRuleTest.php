<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Rules\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberRuleTest extends TestCase
{
    private function passes(string $value): bool
    {
        $failed = false;
        (new PhoneNumber)->validate('phone', $value, function () use (&$failed): void {
            $failed = true;
        });

        return ! $failed;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validNumbers(): array
    {
        return [
            'mobile' => ['081234567890'],
            'international' => ['+62 812-3456-7890'],
            'country code without plus' => ['6281234567890'],
            'landline' => ['(021) 555 1234'],
            'dotted' => ['0812.3456.7890'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidNumbers(): array
    {
        return [
            'letters' => ['abc'],
            'mixed letters' => ['0812abc4567'],
            'too short' => ['0812345'],
            'too long' => ['+62 812 3456 7890 1234'],
            'plus in the middle' => ['0812+34567890'],
            'empty' => [''],
        ];
    }

    #[DataProvider('validNumbers')]
    public function test_accepts_common_formats(string $value): void
    {
        $this->assertTrue($this->passes($value));
    }

    #[DataProvider('invalidNumbers')]
    public function test_rejects_invalid_formats(string $value): void
    {
        $this->assertFalse($this->passes($value));
    }
}
