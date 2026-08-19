<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidDui implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $dui = (string) $value;

        if (! preg_match('/^\d{8}-\d$/', $dui)) {
            $fail('El DUI debe tener el formato ########-#.');
            return;
        }

        $digits = str_replace('-', '', $dui);

        if (count(array_unique(str_split($digits))) === 1) {
            $fail('El DUI no puede estar formado por un solo digito repetido.');
            return;
        }

        $sum = 0;
        for ($index = 0; $index < 8; $index++) {
            $sum += (int) $digits[$index] * (9 - $index);
        }

        $checkDigit = (10 - ($sum % 10)) % 10;

        if ($checkDigit !== (int) $digits[8]) {
            $fail('El DUI ingresado no tiene un digito verificador valido.');
        }
    }
}
