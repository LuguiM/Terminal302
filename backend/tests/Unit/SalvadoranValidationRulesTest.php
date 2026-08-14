<?php

namespace Tests\Unit;

use App\Rules\ValidDui;
use App\Rules\ValidNit;
use App\Rules\ValidSalvadoranPhone;
use Illuminate\Contracts\Validation\ValidationRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SalvadoranValidationRulesTest extends TestCase
{
    #[DataProvider('validValues')]
    public function test_accepts_valid_salvadoran_values(ValidationRule $rule, string $value): void
    {
        $this->assertNull($this->validationMessage($rule, $value));
    }

    #[DataProvider('invalidValues')]
    public function test_rejects_invalid_salvadoran_values(ValidationRule $rule, string $value): void
    {
        $this->assertNotNull($this->validationMessage($rule, $value));
    }

    public static function validValues(): array
    {
        return [
            'DUI con verificador valido' => [new ValidDui, '12345678-4'],
            'NIT con verificador valido' => [new ValidNit, '0614-290695-101-0'],
            'telefono fijo' => [new ValidSalvadoranPhone, '2345-6789'],
            'telefono movil prefijo 6' => [new ValidSalvadoranPhone, '6123-4567'],
            'telefono movil prefijo 7' => [new ValidSalvadoranPhone, '7123-4567'],
        ];
    }

    public static function invalidValues(): array
    {
        return [
            'DUI sin guion' => [new ValidDui, '123456784'],
            'DUI con verificador incorrecto' => [new ValidDui, '88888888-6'],
            'DUI repetido' => [new ValidDui, '88888888-8'],
            'NIT sin guiones' => [new ValidNit, '06142906951010'],
            'NIT con verificador incorrecto' => [new ValidNit, '0614-290695-101-3'],
            'NIT repetido' => [new ValidNit, '0000-000000-000-0'],
            'telefono con prefijo invalido' => [new ValidSalvadoranPhone, '5123-4567'],
            'telefono internacional' => [new ValidSalvadoranPhone, '+503 2345-6789'],
            'telefono repetido' => [new ValidSalvadoranPhone, '7777-7777'],
        ];
    }

    private function validationMessage(ValidationRule $rule, string $value): ?string
    {
        $message = null;
        $rule->validate('field', $value, function (string $failure) use (&$message): void {
            $message = $failure;
        });

        return $message;
    }
}
