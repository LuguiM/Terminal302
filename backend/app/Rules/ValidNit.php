<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidNit implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $nit = (string) $value;

        if (! preg_match('/^\d{4}-\d{6}-\d{3}-\d$/', $nit)) {
            $fail('El NIT debe tener el formato ####-######-###-#.');
            return;
        }

        $digits = str_replace('-', '', $nit);

        if (count(array_unique(str_split($digits))) === 1) {
            $fail('El NIT no puede estar formado por un solo digito repetido.');
            return;
        }

        $sum = 0;
        $sequence = (int) substr($digits, 10, 3);

        for ($index = 0; $index < 13; $index++) {
            $weight = $sequence <= 100
                ? 14 - $index
                : (($index + 3) <= 9 ? $index + 3 : $index - 5);
            $sum += (int) $digits[$index] * $weight;
        }

        $checkDigit = 11 - ($sum % 11);
        $checkDigit = $checkDigit >= 10 ? 0 : $checkDigit;

        if ($checkDigit !== (int) $digits[13]) {
            $fail('El NIT ingresado no tiene un digito verificador valido.');
        }
    }
}
