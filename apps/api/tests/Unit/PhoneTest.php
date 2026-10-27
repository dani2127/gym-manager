<?php

namespace Tests\Unit;

use App\Support\Phone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneTest extends TestCase
{
    #[DataProvider('validNumbers')]
    public function test_normalizes_ethiopian_mobiles_to_e164(string $input, string $expected): void
    {
        $this->assertSame($expected, Phone::normalize($input));
    }

    public static function validNumbers(): array
    {
        return [
            'local with 0' => ['0911234567', '+251911234567'],
            'spaced' => ['0911 23 45 67', '+251911234567'],
            'without prefix' => ['911234567', '+251911234567'],
            'international' => ['+251911234567', '+251911234567'],
            'international no plus' => ['251911234567', '+251911234567'],
            'safaricom' => ['0712345678', '+251712345678'],
        ];
    }

    #[DataProvider('invalidNumbers')]
    public function test_rejects_non_mobile_numbers(?string $input): void
    {
        $this->assertNull(Phone::normalize($input));
    }

    public static function invalidNumbers(): array
    {
        return [
            'null' => [null],
            'too short' => ['09112345'],
            'landline' => ['0111234567'],
            'other country' => ['+254712345678'],
            'letters' => ['abc'],
        ];
    }
}
