<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\PhoneHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneHelperTest extends TestCase
{
    /**
     * Keep in sync with `normalizePhoneNumber()` in resources/js/lib/validators.js.
     *
     * @return array<string, array{string, string|null}>
     */
    public static function cases(): array
    {
        return [
            'local mobile' => ['081234567890', '+6281234567890'],
            'local with dashes' => ['0812-3456-7890', '+6281234567890'],
            'local with spaces and dots' => ['0812 3456.7890', '+6281234567890'],
            'landline with brackets' => ['(021) 555 1234', '+62215551234'],
            'landline with dash' => ['0341-111222', '+62341111222'],
            'country code without plus' => ['6281234567890', '+6281234567890'],
            'plus country code' => ['+62 812-3456-7890', '+6281234567890'],
            'stray trunk zero after +62' => ['+62 0812 3456 7890', '+6281234567890'],
            'stray trunk zero after 62' => ['620812345678', '+62812345678'],
            'double zero prefix' => ['0062812345678', '+62812345678'],
            'missing leading zero' => ['81234567890', '+6281234567890'],
            'already normalized' => ['+6281234567890', '+6281234567890'],
            'foreign number' => ['+65 6123 4567', '+6561234567'],
            'surrounding whitespace' => ['  081234567890  ', '+6281234567890'],
            'blank' => ['   ', null],
            'letters are left for the validation rule' => ['0812abc4567', '0812abc4567'],
            'text is left for the validation rule' => ['nomor-saya', 'nomor-saya'],
        ];
    }

    #[DataProvider('cases')]
    public function test_normalizes_to_e164(string $input, ?string $expected): void
    {
        $this->assertSame($expected, PhoneHelper::normalize($input));
    }

    public function test_null_becomes_null_and_normalizing_twice_is_stable(): void
    {
        $this->assertNull(PhoneHelper::normalize(null));

        foreach (self::cases() as [$input]) {
            $once = PhoneHelper::normalize($input);
            $this->assertSame($once, PhoneHelper::normalize($once));
        }
    }
}
